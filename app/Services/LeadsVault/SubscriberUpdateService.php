<?php

namespace App\Services\LeadsVault;

use App\Models\LeadNormalized;
use App\Support\Brazil\Cpf;
use App\Support\LeadsVault\StandardColumnsSchema;
use Illuminate\Support\Str;

class SubscriberUpdateService
{
    public function normalizeOverrideColumnKey(string $columnKey): string
    {
        return StandardColumnsSchema::canonicalFromKey($columnKey) ?? trim($columnKey);
    }

    public function normalizeValueForColumn(string $columnKey, mixed $raw): ?string
    {
        $value = $raw !== null ? trim((string) $raw) : null;
        if ($value === '') {
            return null;
        }

        return match ($columnKey) {
            'lead', 'name', 'nome' => $this->normalizeLeadName($value),
            'email' => $this->normalizeEmail($value),
            'cpf' => $this->normalizeCpf($value),
            'phone' => $this->normalizeBrPhone($value),
            'city' => $this->normalizeCity($value),
            'uf' => $this->normalizeUf($value),
            'sex' => $this->normalizeSex($value),
            'score' => $this->normalizeScore($value),
            default => $value,
        };
    }

    public function extractBaseValue(LeadNormalized $lead, string $columnKey): mixed
    {
        return match ($columnKey) {
            'nome', 'lead', 'name' => $lead->name,
            'email' => $lead->email,
            'cpf' => $lead->cpf,
            'phone' => $lead->phone_e164,
            'city' => $lead->city,
            'uf' => $lead->uf,
            'sex' => $lead->sex,
            'score' => $lead->score,
            default => $this->decodeExtrasJson($lead->extras_json)[$columnKey] ?? null,
        };
    }

    public function applyOverrideToLead(LeadNormalized $lead, string $columnKey, mixed $value, array &$extras): bool
    {
        $normalizedKey = $this->normalizeOverrideColumnKey($columnKey);

        switch ($normalizedKey) {
            case 'nome':
                $lead->name = $value;
                return true;
            case 'email':
                $lead->email = $value;
                return true;
            case 'cpf':
                $lead->cpf = $value;
                return true;
            case 'phone':
                $lead->phone = $value;
                $lead->phone_e164 = $value;
                return true;
            case 'city':
                $lead->city = $value;
                return true;
            case 'uf':
                $lead->uf = $value;
                return true;
            case 'sex':
                $lead->sex = $value;
                return true;
            case 'score':
                $lead->score = is_numeric($value) ? (float) $value : $value;
                return true;
            default:
                if ($value === null) {
                    unset($extras[$normalizedKey]);
                } else {
                    $extras[$normalizedKey] = $value;
                }
                return false;
        }
    }

    public function updateSubscriberFromAdmin(
        LeadNormalized $subscriber,
        array $data,
        bool $optinEmail,
        bool $optinSms,
        bool $optinWhatsapp
    ): array {
        $before = $this->snapshot($subscriber);

        $subscriber->name = trim((string) ($data['name'] ?? '')) ?: null;
        $subscriber->cpf = trim((string) ($data['cpf'] ?? '')) ?: null;
        $subscriber->email = trim((string) ($data['email'] ?? '')) ?: null;
        $subscriber->phone_e164 = trim((string) ($data['phone_e164'] ?? '')) ?: null;
        $subscriber->sex = trim((string) ($data['sex'] ?? '')) ?: null;
        $subscriber->whatsapp_e164 = trim((string) ($data['whatsapp_e164'] ?? '')) ?: null;
        $subscriber->city = trim((string) ($data['city'] ?? '')) ?: null;
        $subscriber->uf = trim((string) ($data['uf'] ?? '')) ?: null;
        $subscriber->entity_type = trim((string) ($data['entity_type'] ?? '')) ?: null;
        $subscriber->lifecycle_stage = trim((string) ($data['lifecycle_stage'] ?? '')) ?: null;
        $subscriber->score = isset($data['score']) ? max(0, min(100, (int) $data['score'])) : (int) ($subscriber->score ?? 0);
        $subscriber->consent_source = trim((string) ($data['consent_source'] ?? '')) ?: null;

        $subscriber->optin_email = $optinEmail;
        $subscriber->optin_sms = $optinSms;
        $subscriber->optin_whatsapp = $optinWhatsapp;

        $currentExtras = is_array($subscriber->extras_json ?? null) ? $subscriber->extras_json : [];
        foreach (array_keys($currentExtras) as $existingKey) {
            if (StandardColumnsSchema::canonicalFromKey((string) $existingKey) === 'data_nascimento') {
                unset($currentExtras[$existingKey]);
            }
        }

        $birthDate = trim((string) ($data['data_nascimento'] ?? ''));
        if ($birthDate !== '') {
            $currentExtras['data_nascimento'] = $birthDate;
        }

        $incomingExtras = $data['extras'] ?? [];
        if (is_array($incomingExtras)) {
            foreach ($incomingExtras as $key => $value) {
                $safeKey = trim((string) $key);
                if ($safeKey === '') {
                    continue;
                }
                if (StandardColumnsSchema::isStandard($safeKey)) {
                    continue;
                }
                $safeValue = trim((string) ($value ?? ''));
                if ($safeValue === '') {
                    unset($currentExtras[$safeKey]);
                    continue;
                }
                $currentExtras[$safeKey] = $safeValue;
            }
        }
        ksort($currentExtras);
        $subscriber->extras_json = $currentExtras;

        if ($subscriber->optin_email || $subscriber->optin_sms || $subscriber->optin_whatsapp) {
            if (!$subscriber->consent_at) {
                $subscriber->consent_at = now();
            }
        } else {
            $subscriber->consent_at = null;
        }

        $subscriber->save();

        $after = $this->snapshot($subscriber);
        $diff = [];
        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $diff[$field] = ['from' => $before[$field] ?? null, 'to' => $value];
            }
        }

        return [
            'before' => $before,
            'after' => $after,
            'diff' => $diff,
        ];
    }

    private function snapshot(LeadNormalized $subscriber): array
    {
        return [
            'name' => (string) ($subscriber->name ?? ''),
            'cpf' => (string) ($subscriber->cpf ?? ''),
            'email' => (string) ($subscriber->email ?? ''),
            'phone_e164' => (string) ($subscriber->phone_e164 ?? ''),
            'sex' => (string) ($subscriber->sex ?? ''),
            'whatsapp_e164' => (string) ($subscriber->whatsapp_e164 ?? ''),
            'city' => (string) ($subscriber->city ?? ''),
            'uf' => (string) ($subscriber->uf ?? ''),
            'entity_type' => (string) ($subscriber->entity_type ?? ''),
            'lifecycle_stage' => (string) ($subscriber->lifecycle_stage ?? ''),
            'score' => (int) ($subscriber->score ?? 0),
            'consent_source' => (string) ($subscriber->consent_source ?? ''),
            'optin_email' => (bool) ($subscriber->optin_email ?? false),
            'optin_sms' => (bool) ($subscriber->optin_sms ?? false),
            'optin_whatsapp' => (bool) ($subscriber->optin_whatsapp ?? false),
            'consent_at' => $subscriber->consent_at ? (string) $subscriber->consent_at : null,
            'extras_json' => is_array($subscriber->extras_json ?? null) ? $subscriber->extras_json : [],
        ];
    }

    private function decodeExtrasJson(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function normalizeLeadName(string $value): string
    {
        $clean = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $clean = trim($clean);
        if ($clean === '') {
            throw new \InvalidArgumentException('Nome inválido.');
        }
        return Str::title(Str::lower($clean));
    }

    private function normalizeEmail(string $value): string
    {
        $email = Str::lower(trim($value));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email inválido.');
        }
        return $email;
    }

    private function normalizeCpf(string $value): string
    {
        $digits = Cpf::normalize($value);
        if (!$digits) {
            throw new \InvalidArgumentException('CPF inválido. Verifique os dígitos informados.');
        }
        return $digits;
    }

    private function normalizeBrPhone(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0055')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '055')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '55') && strlen($digits) > 11) {
            $digits = substr($digits, 2);
        }

        if (!in_array(strlen($digits), [10, 11], true)) {
            throw new \InvalidArgumentException('Telefone inválido. Use DDD + número (10 ou 11 dígitos).');
        }

        return '+55' . $digits;
    }

    private function normalizeCity(string $value): string
    {
        $clean = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return Str::title(Str::lower(trim($clean)));
    }

    private function normalizeUf(string $value): string
    {
        $uf = Str::upper(trim($value));
        $uf = preg_replace('/[^A-Z]/', '', $uf) ?? $uf;
        if (strlen($uf) !== 2) {
            throw new \InvalidArgumentException('UF inválida. Use 2 letras.');
        }
        return $uf;
    }

    private function normalizeSex(string $value): string
    {
        $sex = Str::upper(trim($value));
        if (!in_array($sex, ['M', 'F'], true)) {
            throw new \InvalidArgumentException('Sexo inválido. Use apenas M ou F.');
        }
        return $sex;
    }

    private function normalizeScore(string $value): string
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Score inválido. Use apenas números.');
        }
        $score = (int) round((float) $value);
        if ($score < 0 || $score > 100) {
            throw new \InvalidArgumentException('Score deve estar entre 0 e 100.');
        }
        return (string) $score;
    }
}
