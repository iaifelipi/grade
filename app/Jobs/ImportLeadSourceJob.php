<?php

namespace App\Jobs;

use App\Models\LeadSource;
use App\Support\TenantStorage;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Jobs\NormalizeLeadsJob;
use App\Support\LeadsVault\LeadIdentity;

class ImportLeadSourceJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(public int $sourceId)
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $source = LeadSource::find($this->sourceId);
        if (!$source) return;

        $source->update([
            'status'           => 'importing',
            'progress_percent' => 0,
            'processed_rows'   => 0,
            'inserted_rows'    => 0,
            'skipped_rows'     => 0,
            'last_error'       => null,
            'started_at'       => now(),
        ]);

        try {
            $tenantUuid = $source->tenant_uuid;

            $path = TenantStorage::privateAbsolutePath($source->file_path);

            $fh = fopen($path, 'rb');
            if (!$fh) {
                $source->update([
                    'status'      => 'failed',
                    'last_error'  => 'Falha ao abrir arquivo.',
                    'finished_at' => now(),
                ]);
                return;
            }

        $firstLine = fgets($fh);
        if ($firstLine === false) {
            fclose($fh);
            $source->update([
                'status'      => 'failed',
                'last_error'  => 'Arquivo vazio.',
                'finished_at' => now(),
            ]);
            return;
        }
        rewind($fh);

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = fgetcsv($fh, 0, $delimiter, '"', '\\');
        if (!$header) {
            fclose($fh);
            $source->update([
                'status'      => 'failed',
                'last_error'  => 'Header inválido.',
                'finished_at' => now(),
            ]);
            return;
        }

        $toUtf8 = function ($value) {
            if ($value === null || $value === false) {
                return null;
            }
            if (!is_string($value)) {
                return $value;
            }
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
            if (function_exists('mb_check_encoding')) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
                    return $converted !== false ? $converted : $value;
                }
                return $value;
            }
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
            return $converted !== false ? $converted : $value;
        };

        $header = array_map($toUtf8, $header);
        $header = array_map(fn($h) => is_string($h) ? trim($h) : $h, $header);
        $colCount = count($header);

        $map = [];
        foreach ($header as $idx => $h) {
            $key = strtoupper(trim((string) $h));
            if ($key !== '') {
                $map[$key] = $idx;
                $norm = preg_replace('/[^A-Z0-9]+/', '_', $key);
                if ($norm !== '') {
                    $map[$norm] = $idx;
                }
            }
        }

        DB::disableQueryLog();

        $dataStartPos = ftell($fh);
        $totalRows = 0;
        while (($rowCount = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            $totalRows++;
        }
        rewind($fh);
        fseek($fh, $dataStartPos);

        $source->update([
            'total_rows' => $totalRows,
        ]);

        $rowNum = 0;
        $insertedTotal = 0;
        $batch = [];
        $BATCH_SIZE = 1000;
        $lastProgressUpdate = 0.0;

        while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {

            $source->refresh();
            if ($source->cancel_requested) {
                fclose($fh);
                $source->update([
                    'status'      => 'cancelled',
                    'finished_at' => now(),
                ]);
                return;
            }

            $rowNum++;

            $row = array_map($toUtf8, $row);
            if (count($row) < $colCount) {
                $row = array_pad($row, $colCount, null);
            } elseif (count($row) > $colCount) {
                $row = array_slice($row, 0, $colCount);
            }

            $getCell = function (string $key) use ($map, $row) {
                $key = strtoupper(trim($key));
                if (isset($map[$key])) {
                    $idx = $map[$key];
                    return $row[$idx] ?? null;
                }
                $norm = preg_replace('/[^A-Z0-9]+/', '_', $key);
                if ($norm !== '' && isset($map[$norm])) {
                    $idx = $map[$norm];
                    return $row[$idx] ?? null;
                }
                return null;
            };

            $cpfRaw = $getCell('CPF') ?? $getCell('CPF_CNPJ');
            $email  = $getCell('EMAIL');

            $phoneRaw = $getCell('TEL_CELULAR') ?? $getCell('PHONE_E164');

            $nome =
                trim(
                    (string) ($getCell('NOME') ?? '') . ' ' .
                    (string) ($getCell('SOBRENOME') ?? '')
                ) ?: null;

            $cpf = LeadIdentity::normalizeCPF($cpfRaw !== null ? (string) $cpfRaw : null);

            $phone = $phoneRaw !== null
                ? (preg_replace('/\D/','',(string) $phoneRaw) ?: null)
                : null;

            $payloadAssoc = array_combine($header, $row);

            $batch[] = [
                'tenant_uuid'    => $tenantUuid,
                'lead_source_id' => $source->id,
                'row_num'        => $rowNum,
                'cpf'            => $cpf,
                'email'          => $email,
                'phone_e164'     => $phone,
                'name'           => $nome,
                'identity_key'   => (string) Str::uuid(),

                // ✅ CORREÇÃO CRÍTICA
                'payload_json'   => json_encode(
                    $payloadAssoc,
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                ),

                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            if (count($batch) >= $BATCH_SIZE) {
                $inserted = (int) DB::table('lead_raw')->insertOrIgnore($batch);
                $insertedTotal += $inserted;
                $batch = [];

                $now = microtime(true);
                if ($now - $lastProgressUpdate > 0.5) {
                    $progress = $totalRows > 0 ? (int) round(($rowNum / $totalRows) * 100) : 0;
                    $source->update([
                        'processed_rows'   => $rowNum,
                        'inserted_rows'    => $insertedTotal,
                        'progress_percent' => min(100, $progress),
                    ]);
                    $lastProgressUpdate = $now;
                }
            }
        }

        if ($batch) {
            $inserted = (int) DB::table('lead_raw')->insertOrIgnore($batch);
            $insertedTotal += $inserted;
        }

            fclose($fh);

            // normalize em fila separada
            $source->update([
                'processed_rows'   => $rowNum,
                'inserted_rows'    => $insertedTotal,
                'progress_percent' => 100,
                'status'           => 'normalizing',
            ]);

            NormalizeLeadsJob::dispatch($tenantUuid, $source->id)
                ->onQueue('normalize');
        } catch (\Throwable $e) {
            $source->update([
                'status'      => 'failed',
                'last_error'  => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }
}
