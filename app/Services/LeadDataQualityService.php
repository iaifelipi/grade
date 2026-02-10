<?php

namespace App\Services;

use App\Models\LeadOverride;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File as HttpFile;

class LeadDataQualityService
{
    private array $baseMap = [
        'nome' => 'name',
        'lead' => 'name',
        'name' => 'name',
        'email' => 'email',
        'cpf' => 'cpf',
        'phone' => 'phone_e164',
        'city' => 'city',
        'uf' => 'uf',
        'sex' => 'sex',
        'score' => 'score',
    ];

    public function mapKeyToColumn(string $key): ?string
    {
        $key = strtolower(trim($key));
        return $this->baseMap[$key] ?? null;
    }

    public function getValue($row, string $key)
    {
        $column = $this->mapKeyToColumn($key);
        if ($column) {
            return data_get($row, $column);
        }

        $extras = data_get($row, 'extras_json');
        if (is_string($extras)) {
            $decoded = json_decode($extras, true);
            $extras = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($extras)) {
            $extras = [];
        }

        return $extras[$key] ?? null;
    }

    public function applyOverridesToNormalizedRows(Collection $rows, int $sourceId, string $tenantUuid): Collection
    {
        if ($rows->isEmpty() || !Schema::hasTable('lead_overrides')) {
            return $rows;
        }

        $ids = $rows->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        if (!$ids) {
            return $rows;
        }

        $overrides = LeadOverride::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->whereIn('lead_id', $ids)
            ->get(['lead_id', 'column_key', 'value_text'])
            ->groupBy('lead_id');

        if ($overrides->isEmpty()) {
            return $rows;
        }

        return $rows->map(function ($row) use ($overrides) {
            $leadId = (int) ($row->id ?? 0);
            $leadOverrides = $overrides->get($leadId);
            if (!$leadOverrides instanceof Collection || $leadOverrides->isEmpty()) {
                return $row;
            }

            $extras = $this->decodeExtras($row->extras_json ?? null);
            foreach ($leadOverrides as $override) {
                $key = (string) $override->column_key;
                $value = $override->value_text;

                $applied = false;
                switch ($key) {
                    case 'nome':
                    case 'lead':
                    case 'name':
                        $row->name = $value;
                        $applied = true;
                        break;
                    case 'email':
                        $row->email = $value;
                        $applied = true;
                        break;
                    case 'cpf':
                        $row->cpf = $value;
                        $applied = true;
                        break;
                    case 'phone':
                        $row->phone_e164 = $value;
                        if (property_exists($row, 'phone')) {
                            $row->phone = $value;
                        }
                        $applied = true;
                        break;
                    case 'city':
                        $row->city = $value;
                        $applied = true;
                        break;
                    case 'uf':
                        $row->uf = $value;
                        $applied = true;
                        break;
                    case 'sex':
                        $row->sex = $value;
                        $applied = true;
                        break;
                    case 'score':
                        $row->score = is_numeric($value) ? (float) $value : $value;
                        $applied = true;
                        break;
                }

                if (!$applied) {
                    if ($value === null) {
                        unset($extras[$key]);
                    } else {
                        $extras[$key] = $value;
                    }
                }
            }

            $row->extras_json = $extras ? json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
            return $row;
        });
    }

    public function applyRules($value, array $rules)
    {
        $current = $value;

        foreach ($rules as $rule) {
            $current = $this->applyRule($current, $rule);
        }

        return $current;
    }

    private function applyRule($value, string $rule)
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        $str = (string) $value;

        return match ($rule) {
            'trim' => trim($str),
            'upper' => Str::upper($str),
            'lower' => Str::lower($str),
            'title' => Str::title($str),
            'remove_accents' => $this->removeAccents($str),
            'digits_only' => preg_replace('/\D+/', '', $str) ?? '',
            'date_iso' => $this->normalizeDate($str),
            'null_if_empty' => trim($str) === '' ? null : $str,
            default => $str,
        };
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'd/m/y'];
        foreach ($formats as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $value);
                if ($dt !== false) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function removeAccents(string $value): string
    {
        $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($normalized === false || $normalized === null) {
            return $value;
        }
        return $normalized;
    }

    public function exportEditedCsv(int $sourceId, string $columnKey, array $rules): array
    {
        $tenantUuid = TenantStorage::requireTenantUuid();
        $columnKey = trim($columnKey);

        $firstRow = DB::table('lead_raw')
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->orderBy('id')
            ->value('payload_json');

        if (!$firstRow) {
            throw new \RuntimeException('Fonte sem dados para exportar.');
        }

        $header = [];
        $headerIndex = [];

        $addHeader = function (array $row) use (&$header, &$headerIndex) {
            foreach ($row as $key => $value) {
                if (!array_key_exists($key, $headerIndex)) {
                    $headerIndex[$key] = count($header);
                    $header[] = $key;
                }
            }
        };

        $decodeRow = function ($payload) {
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                return is_array($decoded) ? $decoded : [];
            }
            if (is_array($payload)) {
                return $payload;
            }
            return [];
        };

        $addHeader($decodeRow($firstRow));

        DB::table('lead_raw')
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->select(['id', 'payload_json'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($addHeader, $decodeRow) {
                foreach ($rows as $row) {
                    $payload = $decodeRow($row->payload_json);
                    if ($payload) {
                        $addHeader($payload);
                    }
                }
            });

        if (!array_key_exists($columnKey, $headerIndex)) {
            $headerIndex[$columnKey] = count($header);
            $header[] = $columnKey;
        }

        $dir = TenantStorage::importsDir($tenantUuid);
        TenantStorage::ensurePrivateDir($dir);

        $filename = 'edited_' . $sourceId . '_' . now()->format('Ymd_His') . '.csv';
        $relative = TenantStorage::normalizePath($dir . '/' . $filename);
        $absolute = TenantStorage::privateAbsolutePath($relative);

        $tmpPath = tempnam(sys_get_temp_dir(), 'edited_csv_');
        if (!$tmpPath) {
            throw new \RuntimeException('Falha ao criar arquivo temporário.');
        }

        $fh = fopen($tmpPath, 'wb');
        if (!$fh) {
            throw new \RuntimeException('Falha ao abrir arquivo temporário.');
        }

        fputcsv($fh, $header, ';');

        $rowNumToLeadId = DB::table('leads_normalized')
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->pluck('id', 'row_num')
            ->all();

        $overridesByLead = [];
        if (Schema::hasTable('lead_overrides') && $rowNumToLeadId) {
            $leadIds = array_values(array_unique(array_map('intval', array_values($rowNumToLeadId))));
            if ($leadIds) {
                $overrideRows = LeadOverride::query()
                    ->where('tenant_uuid', $tenantUuid)
                    ->where('lead_source_id', $sourceId)
                    ->whereIn('lead_id', $leadIds)
                    ->get(['lead_id', 'column_key', 'value_text']);

                foreach ($overrideRows as $override) {
                    $lid = (int) $override->lead_id;
                    if (!isset($overridesByLead[$lid])) {
                        $overridesByLead[$lid] = [];
                    }
                    $overridesByLead[$lid][(string) $override->column_key] = $override->value_text;
                }
            }
        }

        DB::table('lead_raw')
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->select(['id', 'row_num', 'payload_json'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($decodeRow, $fh, $header, $columnKey, $rules, $rowNumToLeadId, $overridesByLead) {
                foreach ($rows as $row) {
                    $payload = $decodeRow($row->payload_json);
                    $leadId = $rowNumToLeadId[(int) $row->row_num] ?? null;
                    if ($leadId && isset($overridesByLead[(int) $leadId])) {
                        foreach ($overridesByLead[(int) $leadId] as $key => $value) {
                            $this->applyOverrideToPayload($payload, (string) $key, $value);
                        }
                    }

                    $value = $payload[$columnKey] ?? null;
                    $payload[$columnKey] = $this->applyRules($value, $rules);

                    $line = [];
                    foreach ($header as $key) {
                        $cell = $payload[$key] ?? null;
                        if (is_array($cell) || is_object($cell)) {
                            $cell = json_encode($cell, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                        }
                        $line[] = $cell;
                    }
                    fputcsv($fh, $line, ';');
                }
            });

        fclose($fh);

        Storage::disk('private')->putFileAs($dir, new HttpFile($tmpPath), $filename);
        @unlink($tmpPath);

        TenantStorage::fixPrivateDirPermsForFile($relative);
        TenantStorage::fixPrivateFilePerms($relative);

        return [
            'path' => $relative,
            'size' => (int) Storage::disk('private')->size($relative),
            'hash' => (string) sha1_file($absolute),
        ];
    }

    public function exportSourceWithOverridesCsv(int $sourceId): array
    {
        $tenantUuid = TenantStorage::requireTenantUuid();

        $firstRow = DB::table('lead_raw')
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->orderBy('id')
            ->value('payload_json');

        if (!$firstRow) {
            throw new \RuntimeException('Fonte sem dados para exportar.');
        }

        $header = [];
        $headerIndex = [];
        $decodeRow = function ($payload) {
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                return is_array($decoded) ? $decoded : [];
            }
            if (is_array($payload)) {
                return $payload;
            }
            return [];
        };
        $addHeader = function (array $row) use (&$header, &$headerIndex) {
            foreach ($row as $key => $value) {
                if (!array_key_exists($key, $headerIndex)) {
                    $headerIndex[$key] = count($header);
                    $header[] = $key;
                }
            }
        };

        $addHeader($decodeRow($firstRow));
        DB::table('lead_raw')
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->select(['id', 'payload_json'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($addHeader, $decodeRow) {
                foreach ($rows as $row) {
                    $payload = $decodeRow($row->payload_json);
                    if ($payload) {
                        $addHeader($payload);
                    }
                }
            });

        $rowNumToLeadId = DB::table('leads_normalized')
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->pluck('id', 'row_num')
            ->all();

        $overridesByLead = [];
        if (Schema::hasTable('lead_overrides') && $rowNumToLeadId) {
            $leadIds = array_values(array_unique(array_map('intval', array_values($rowNumToLeadId))));
            if ($leadIds) {
                $overrideRows = LeadOverride::query()
                    ->where('tenant_uuid', $tenantUuid)
                    ->where('lead_source_id', $sourceId)
                    ->whereIn('lead_id', $leadIds)
                    ->get(['lead_id', 'column_key', 'value_text']);

                foreach ($overrideRows as $override) {
                    $lid = (int) $override->lead_id;
                    if (!isset($overridesByLead[$lid])) {
                        $overridesByLead[$lid] = [];
                    }
                    $overridesByLead[$lid][(string) $override->column_key] = $override->value_text;
                }
            }
        }

        $dir = TenantStorage::importsDir($tenantUuid);
        TenantStorage::ensurePrivateDir($dir);

        $filename = 'edited_overrides_' . $sourceId . '_' . now()->format('Ymd_His') . '.csv';
        $relative = TenantStorage::normalizePath($dir . '/' . $filename);
        $absolute = TenantStorage::privateAbsolutePath($relative);

        $tmpPath = tempnam(sys_get_temp_dir(), 'edited_overrides_');
        if (!$tmpPath) {
            throw new \RuntimeException('Falha ao criar arquivo temporário.');
        }

        $fh = fopen($tmpPath, 'wb');
        if (!$fh) {
            throw new \RuntimeException('Falha ao abrir arquivo temporário.');
        }

        fputcsv($fh, $header, ';');

        DB::table('lead_raw')
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->select(['id', 'row_num', 'payload_json'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($decodeRow, $fh, $header, $rowNumToLeadId, $overridesByLead) {
                foreach ($rows as $row) {
                    $payload = $decodeRow($row->payload_json);
                    $leadId = $rowNumToLeadId[(int) $row->row_num] ?? null;
                    if ($leadId && isset($overridesByLead[(int) $leadId])) {
                        foreach ($overridesByLead[(int) $leadId] as $key => $value) {
                            $this->applyOverrideToPayload($payload, (string) $key, $value);
                        }
                    }

                    $line = [];
                    foreach ($header as $key) {
                        $cell = $payload[$key] ?? null;
                        if (is_array($cell) || is_object($cell)) {
                            $cell = json_encode($cell, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                        }
                        $line[] = $cell;
                    }
                    fputcsv($fh, $line, ';');
                }
            });

        fclose($fh);

        Storage::disk('private')->putFileAs($dir, new HttpFile($tmpPath), $filename);
        @unlink($tmpPath);

        TenantStorage::fixPrivateDirPermsForFile($relative);
        TenantStorage::fixPrivateFilePerms($relative);

        return [
            'path' => $relative,
            'size' => (int) Storage::disk('private')->size($relative),
            'hash' => (string) sha1_file($absolute),
        ];
    }

    private function decodeExtras(mixed $extras): array
    {
        if (is_array($extras)) {
            return $extras;
        }

        if (is_string($extras) && $extras !== '') {
            $decoded = json_decode($extras, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function applyOverrideToPayload(array &$payload, string $key, mixed $value): void
    {
        $normalizedKey = strtolower(trim($key));
        $targetColumns = match ($normalizedKey) {
            'nome', 'lead', 'name' => ['NOME', 'name', 'NAME'],
            'phone' => ['TEL_CELULAR', 'PHONE_E164', 'phone_e164', 'TELEFONE'],
            'cpf' => ['CPF', 'CPF_CNPJ', 'cpf'],
            'email' => ['EMAIL', 'email'],
            'city' => ['CIDADE', 'Cidade', 'city', 'MUNICIPIO'],
            'uf' => ['UF', 'uf', 'ESTADO', 'Estado'],
            'sex' => ['SEXO', 'Sexo', 'GENERO', 'Genero'],
            'score' => ['SCORE', 'score'],
            default => [$key],
        };

        $matched = false;
        foreach ($targetColumns as $candidate) {
            $realKey = $this->findPayloadKey($payload, $candidate);
            if ($realKey !== null) {
                $payload[$realKey] = $value;
                $matched = true;
            }
        }

        if (!$matched) {
            $fallback = match ($normalizedKey) {
                'nome' => 'NOME',
                'lead' => 'NOME',
                'phone' => 'TEL_CELULAR',
                'cpf' => 'CPF',
                'email' => 'EMAIL',
                'city' => 'CIDADE',
                'uf' => 'UF',
                'sex' => 'SEXO',
                'score' => 'SCORE',
                default => $key,
            };
            $payload[$fallback] = $value;
        }

        if (in_array($normalizedKey, ['nome', 'lead', 'name'], true)) {
            $sobrenomeKey = $this->findPayloadKey($payload, 'SOBRENOME');
            if ($sobrenomeKey !== null) {
                $payload[$sobrenomeKey] = '';
            }
        }
    }

    private function findPayloadKey(array $payload, string $candidate): ?string
    {
        if (array_key_exists($candidate, $payload)) {
            return $candidate;
        }

        $candidateNorm = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', (string) $candidate) ?? '');
        foreach (array_keys($payload) as $existingKey) {
            if (!is_string($existingKey)) {
                continue;
            }
            if (strcasecmp($existingKey, $candidate) === 0) {
                return $existingKey;
            }
            $existingNorm = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $existingKey) ?? '');
            if ($candidateNorm !== '' && $existingNorm === $candidateNorm) {
                return $existingKey;
            }
        }

        return null;
    }
}
