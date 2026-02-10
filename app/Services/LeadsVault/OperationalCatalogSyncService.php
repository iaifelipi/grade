<?php

namespace App\Services\LeadsVault;

use App\Models\LeadNormalized;
use App\Models\OperationalChannel;
use App\Models\OperationalConsent;
use App\Models\OperationalIdentity;
use App\Models\OperationalRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationalCatalogSyncService
{
    public function syncLead(int|LeadNormalized $lead): ?OperationalRecord
    {
        $model = $lead instanceof LeadNormalized
            ? $lead
            : LeadNormalized::query()->find($lead);

        if (!$model) {
            return null;
        }

        return DB::transaction(function () use ($model): OperationalRecord {
            $tenantUuid = (string) $model->tenant_uuid;

            $record = OperationalRecord::query()->updateOrCreate(
                [
                    'tenant_uuid' => $tenantUuid,
                    'legacy_lead_id' => (int) $model->id,
                ],
                [
                    'lead_source_id' => (int) ($model->lead_source_id ?? 0) ?: null,
                    'entity_type' => $this->normalizeEnum($model->entity_type, 32) ?? 'lead',
                    'lifecycle_stage' => $this->normalizeEnum($model->lifecycle_stage, 40),
                    'status' => 'active',
                    'display_name' => $this->limitNullable($model->name, 190),
                    'document_number' => $this->limitNullable($model->cpf, 64),
                    'primary_email' => $this->normalizeEmail($model->email),
                    'primary_phone_e164' => $this->normalizePhone($model->phone_e164),
                    'primary_whatsapp_e164' => $this->normalizePhone($model->whatsapp_e164),
                    'city' => $this->limitNullable($model->city, 120),
                    'uf' => $this->normalizeUf($model->uf),
                    'sex' => $this->limitNullable($model->sex, 8),
                    'score' => max(0, min(100, (int) ($model->score ?? 0))),
                    'consent_email' => (bool) $model->optin_email,
                    'consent_sms' => (bool) $model->optin_sms,
                    'consent_whatsapp' => (bool) $model->optin_whatsapp,
                    'consent_source' => $this->limitNullable($model->consent_source, 120),
                    'consent_at' => $model->consent_at,
                    'last_interaction_at' => $model->last_interaction_at,
                    'next_action_at' => $model->next_action_at,
                    'metadata_json' => is_array($model->extras_json) ? $model->extras_json : null,
                ]
            );

            $this->syncChannels($record, $model);
            $this->syncConsents($record, $model);
            $this->syncIdentities($record, $model);
            $this->bindInteractions($record);

            return $record->fresh();
        });
    }

    /**
     * @param array<int,int|string> $leadIds
     */
    public function syncLeadIds(array $leadIds, ?string $tenantUuid = null): int
    {
        $ids = array_values(array_unique(array_map('intval', $leadIds)));
        $ids = array_values(array_filter($ids, fn (int $id): bool => $id > 0));
        if (!$ids) {
            return 0;
        }

        $count = 0;
        $query = LeadNormalized::query()->whereIn('id', $ids)->orderBy('id');
        if (filled($tenantUuid)) {
            $query->where('tenant_uuid', (string) $tenantUuid);
        }

        $query->chunkById(300, function ($rows) use (&$count): void {
            foreach ($rows as $row) {
                if ($this->syncLead($row)) {
                    $count++;
                }
            }
        });

        return $count;
    }

    public function syncBySourceId(int $sourceId, ?string $tenantUuid = null, ?int $limit = null): int
    {
        if ($sourceId <= 0) {
            return 0;
        }

        $count = 0;
        $query = LeadNormalized::query()
            ->where('lead_source_id', $sourceId)
            ->orderBy('id');

        if (filled($tenantUuid)) {
            $query->where('tenant_uuid', (string) $tenantUuid);
        }

        if ($limit !== null && $limit > 0) {
            $rows = $query->limit($limit)->get();
            foreach ($rows as $row) {
                try {
                    if ($this->syncLead($row)) {
                        $count++;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $count;
        }

        $query->chunkById(300, function ($rows) use (&$count): void {
            foreach ($rows as $row) {
                try {
                    if ($this->syncLead($row)) {
                        $count++;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });

        return $count;
    }

    public function syncTenant(?string $tenantUuid = null, ?int $limit = null): int
    {
        $count = 0;
        $query = LeadNormalized::query()->orderBy('id');

        if (filled($tenantUuid)) {
            $query->where('tenant_uuid', (string) $tenantUuid);
        }

        if ($limit !== null && $limit > 0) {
            $rows = $query->limit($limit)->get();
            foreach ($rows as $row) {
                if ($this->syncLead($row)) {
                    $count++;
                }
            }

            return $count;
        }

        $query->chunkById(300, function ($rows) use (&$count): void {
            foreach ($rows as $row) {
                if ($this->syncLead($row)) {
                    $count++;
                }
            }
        });

        return $count;
    }

    private function syncChannels(OperationalRecord $record, LeadNormalized $lead): void
    {
        $channels = [
            'email' => [
                'value' => $this->normalizeEmail($lead->email),
                'can_contact' => (bool) $lead->optin_email,
            ],
            'sms' => [
                'value' => $this->normalizePhone($lead->phone_e164),
                'can_contact' => (bool) $lead->optin_sms,
            ],
            'whatsapp' => [
                'value' => $this->normalizePhone($lead->whatsapp_e164),
                'can_contact' => (bool) $lead->optin_whatsapp,
            ],
        ];

        foreach ($channels as $type => $channel) {
            $value = $channel['value'];
            $canContact = (bool) $channel['can_contact'];

            if (!$value) {
                OperationalChannel::query()
                    ->where('tenant_uuid', $record->tenant_uuid)
                    ->where('operational_record_id', $record->id)
                    ->where('channel_type', $type)
                    ->update([
                        'is_primary' => false,
                        'can_contact' => false,
                        'updated_at' => now(),
                    ]);
                continue;
            }

            OperationalChannel::query()
                ->where('tenant_uuid', $record->tenant_uuid)
                ->where('operational_record_id', $record->id)
                ->where('channel_type', $type)
                ->update([
                    'is_primary' => false,
                    'updated_at' => now(),
                ]);

            OperationalChannel::query()->updateOrCreate(
                [
                    'tenant_uuid' => $record->tenant_uuid,
                    'operational_record_id' => (int) $record->id,
                    'channel_type' => $type,
                    'value_normalized' => $value,
                ],
                [
                    'value' => $value,
                    'is_primary' => true,
                    'can_contact' => $canContact,
                    'last_used_at' => $lead->last_interaction_at,
                ]
            );
        }
    }

    private function syncConsents(OperationalRecord $record, LeadNormalized $lead): void
    {
        $source = $this->limitNullable($lead->consent_source, 120);
        $occurredAt = $lead->consent_at;

        foreach (['email', 'sms', 'whatsapp'] as $channelType) {
            $optin = (bool) $lead->{'optin_' . $channelType};
            $status = $optin ? 'granted' : 'revoked';

            OperationalConsent::query()->updateOrCreate(
                [
                    'tenant_uuid' => $record->tenant_uuid,
                    'operational_record_id' => (int) $record->id,
                    'purpose' => 'marketing',
                    'channel_type' => $channelType,
                ],
                [
                    'status' => $status,
                    'source' => $source,
                    'occurred_at' => $occurredAt,
                    'revoked_at' => $optin ? null : now(),
                ]
            );
        }
    }

    private function syncIdentities(OperationalRecord $record, LeadNormalized $lead): void
    {
        $identities = [
            'cpf' => $this->normalizeDigits($lead->cpf, 32),
            'email' => $this->normalizeEmail($lead->email),
            'phone_e164' => $this->normalizePhone($lead->phone_e164),
            'whatsapp_e164' => $this->normalizePhone($lead->whatsapp_e164),
        ];

        foreach ($identities as $type => $key) {
            if (!$key) {
                continue;
            }

            $identity = OperationalIdentity::query()->firstOrNew([
                'tenant_uuid' => $record->tenant_uuid,
                'identity_type' => $type,
                'identity_key' => $key,
            ]);

            if ($identity->exists && (int) $identity->operational_record_id !== (int) $record->id) {
                continue;
            }

            $identity->operational_record_id = (int) $record->id;
            $identity->is_primary = true;
            $identity->confidence = 100;
            $identity->source = 'leads_normalized_sync';
            $identity->save();
        }
    }

    private function bindInteractions(OperationalRecord $record): void
    {
        $legacyLeadId = (int) ($record->legacy_lead_id ?? 0);
        if ($legacyLeadId <= 0) {
            return;
        }

        DB::table('record_interactions')
            ->where('tenant_uuid', (string) $record->tenant_uuid)
            ->where('lead_id', $legacyLeadId)
            ->whereNull('operational_record_id')
            ->update([
                'operational_record_id' => (int) $record->id,
                'updated_at' => now(),
            ]);
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $email = Str::lower(trim((string) $value));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return Str::limit($email, 190, '');
    }

    private function normalizePhone(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $plus = Str::startsWith($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        $normalized = $plus ? ('+' . $digits) : $digits;
        return Str::limit($normalized, 32, '');
    }

    private function normalizeDigits(mixed $value, int $max): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';
        if ($digits === '') {
            return null;
        }

        return Str::limit($digits, $max, '');
    }

    private function normalizeUf(mixed $value): ?string
    {
        $uf = Str::upper(trim((string) $value));
        return $uf !== '' ? Str::limit($uf, 4, '') : null;
    }

    private function normalizeEnum(mixed $value, int $maxLen): ?string
    {
        $text = Str::lower(trim((string) $value));
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/\s+/', '_', $text) ?? $text;
        $text = preg_replace('/[^a-z0-9_\-]/', '', $text) ?? $text;
        $text = trim($text, '_-');

        return $text !== '' ? Str::limit($text, $maxLen, '') : null;
    }

    private function limitNullable(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return Str::limit($text, $max, '');
    }
}
