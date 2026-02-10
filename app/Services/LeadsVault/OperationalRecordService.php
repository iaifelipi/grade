<?php

namespace App\Services\LeadsVault;

use App\Models\LeadNormalized;
use App\Models\OperationalRecord;
use App\Models\RecordInteraction;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationalRecordService
{
    public function __construct(
        private readonly OperationalCatalogSyncService $catalogSync,
        private readonly OperationalEntityTypeCatalog $entityTypeCatalog,
    )
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function sanitizeUpdates(array $input): array
    {
        $allowed = [
            'entity_type',
            'lifecycle_stage',
            'name',
            'email',
            'phone_e164',
            'whatsapp_e164',
            'city',
            'uf',
            'sex',
            'score',
            'optin_email',
            'optin_sms',
            'optin_whatsapp',
            'consent_source',
            'consent_at',
            'next_action_at',
            'extras_json',
        ];

        $data = Arr::only($input, $allowed);

        if (array_key_exists('entity_type', $data)) {
            $entityType = $this->normalizeTextEnum($data['entity_type'], 32);
            if ($entityType !== null && !$this->entityTypeCatalog->isAllowed($entityType)) {
                throw new \InvalidArgumentException("Tipo de entidade inválido: {$entityType}");
            }
            $data['entity_type'] = $entityType;
        }

        if (array_key_exists('lifecycle_stage', $data)) {
            $data['lifecycle_stage'] = $this->normalizeTextEnum($data['lifecycle_stage'], 40);
        }

        if (array_key_exists('email', $data)) {
            $email = Str::lower(trim((string) $data['email']));
            $data['email'] = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }

        if (array_key_exists('phone_e164', $data)) {
            $data['phone_e164'] = $this->normalizePhone($data['phone_e164']);
        }

        if (array_key_exists('whatsapp_e164', $data)) {
            $data['whatsapp_e164'] = $this->normalizePhone($data['whatsapp_e164']);
        }

        if (array_key_exists('uf', $data)) {
            $uf = Str::upper(trim((string) $data['uf']));
            $data['uf'] = $uf !== '' ? Str::limit($uf, 4, '') : null;
        }

        foreach (['optin_email', 'optin_sms', 'optin_whatsapp'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = (bool) $data[$key];
            }
        }

        if (array_key_exists('score', $data)) {
            $score = is_numeric($data['score']) ? (int) $data['score'] : 0;
            $data['score'] = max(0, min(100, $score));
        }

        if (array_key_exists('consent_source', $data)) {
            $source = trim((string) $data['consent_source']);
            $data['consent_source'] = $source !== '' ? Str::limit($source, 120, '') : null;
        }

        if (array_key_exists('consent_at', $data)) {
            $data['consent_at'] = $this->parseDateTime($data['consent_at']);
        }

        if (array_key_exists('next_action_at', $data)) {
            $data['next_action_at'] = $this->parseDateTime($data['next_action_at']);
        }

        if (array_key_exists('extras_json', $data) && !is_array($data['extras_json'])) {
            $data['extras_json'] = [];
        }

        if (
            (($data['optin_email'] ?? false) || ($data['optin_sms'] ?? false) || ($data['optin_whatsapp'] ?? false))
            && empty($data['consent_at'])
        ) {
            $data['consent_at'] = now();
        }

        return $data;
    }

    public function updateRecord(OperationalRecord $record, array $input): OperationalRecord
    {
        $data = $this->sanitizeUpdates($input);
        $record->fill([
            'entity_type' => $data['entity_type'] ?? $record->entity_type,
            'lifecycle_stage' => $data['lifecycle_stage'] ?? $record->lifecycle_stage,
            'display_name' => $data['name'] ?? $record->display_name,
            'primary_email' => $data['email'] ?? $record->primary_email,
            'primary_phone_e164' => $data['phone_e164'] ?? $record->primary_phone_e164,
            'primary_whatsapp_e164' => $data['whatsapp_e164'] ?? $record->primary_whatsapp_e164,
            'city' => $data['city'] ?? $record->city,
            'uf' => $data['uf'] ?? $record->uf,
            'sex' => $data['sex'] ?? $record->sex,
            'score' => $data['score'] ?? $record->score,
            'consent_email' => $data['optin_email'] ?? $record->consent_email,
            'consent_sms' => $data['optin_sms'] ?? $record->consent_sms,
            'consent_whatsapp' => $data['optin_whatsapp'] ?? $record->consent_whatsapp,
            'consent_source' => $data['consent_source'] ?? $record->consent_source,
            'consent_at' => $data['consent_at'] ?? $record->consent_at,
            'next_action_at' => $data['next_action_at'] ?? $record->next_action_at,
            'metadata_json' => $data['extras_json'] ?? $record->metadata_json,
        ]);
        $record->save();

        $this->syncLegacyLeadFromOperationalRecord($record, $data);
        $this->syncOperationalRelations($record);

        return $record->fresh();
    }

    public function createRecord(array $input, ?int $actorId = null): OperationalRecord
    {
        $data = $this->sanitizeUpdates($input);
        $tenantUuid = (string) app('tenant_uuid');
        if ($tenantUuid === '') {
            throw new \RuntimeException('Tenant não resolvido para criação de registro operacional.');
        }

        $record = OperationalRecord::query()->create([
            'tenant_uuid' => $tenantUuid,
            'legacy_lead_id' => null,
            'lead_source_id' => isset($input['source_id']) ? (int) $input['source_id'] : null,
            'entity_type' => $data['entity_type'] ?? 'lead',
            'lifecycle_stage' => $data['lifecycle_stage'] ?? null,
            'status' => 'active',
            'display_name' => $data['name'] ?? null,
            'primary_email' => $data['email'] ?? null,
            'primary_phone_e164' => $data['phone_e164'] ?? null,
            'primary_whatsapp_e164' => $data['whatsapp_e164'] ?? null,
            'city' => $data['city'] ?? null,
            'uf' => $data['uf'] ?? null,
            'sex' => $data['sex'] ?? null,
            'score' => $data['score'] ?? 0,
            'consent_email' => $data['optin_email'] ?? false,
            'consent_sms' => $data['optin_sms'] ?? false,
            'consent_whatsapp' => $data['optin_whatsapp'] ?? false,
            'consent_source' => $data['consent_source'] ?? null,
            'consent_at' => $data['consent_at'] ?? null,
            'next_action_at' => $data['next_action_at'] ?? null,
            'metadata_json' => $data['extras_json'] ?? null,
        ]);

        $this->logAuditInteraction($record, 'create', $actorId, [
            'input' => Arr::only($input, array_keys($data)),
        ]);

        return $record->fresh();
    }

    /**
     * @param array<int,int> $ids
     */
    public function bulkUpdate(array $ids, array $input): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return 0;
        }

        $data = $this->sanitizeUpdates($input);
        if (!$data) {
            return 0;
        }

        if (array_key_exists('extras_json', $data) && is_array($data['extras_json'])) {
            $data['extras_json'] = json_encode($data['extras_json'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        if (!array_key_exists('updated_at', $data)) {
            $data['updated_at'] = now();
        }

        $updates = [
            'entity_type' => $data['entity_type'] ?? null,
            'lifecycle_stage' => $data['lifecycle_stage'] ?? null,
            'display_name' => $data['name'] ?? null,
            'primary_email' => $data['email'] ?? null,
            'primary_phone_e164' => $data['phone_e164'] ?? null,
            'primary_whatsapp_e164' => $data['whatsapp_e164'] ?? null,
            'city' => $data['city'] ?? null,
            'uf' => $data['uf'] ?? null,
            'sex' => $data['sex'] ?? null,
            'score' => $data['score'] ?? null,
            'consent_email' => $data['optin_email'] ?? null,
            'consent_sms' => $data['optin_sms'] ?? null,
            'consent_whatsapp' => $data['optin_whatsapp'] ?? null,
            'consent_source' => $data['consent_source'] ?? null,
            'consent_at' => $data['consent_at'] ?? null,
            'next_action_at' => $data['next_action_at'] ?? null,
            'metadata_json' => $data['extras_json'] ?? null,
            'updated_at' => $data['updated_at'] ?? now(),
        ];
        $updates = array_filter($updates, fn ($v) => $v !== null);

        $updated = OperationalRecord::query()
            ->whereIn('id', $ids)
            ->update($updates);

        if ($updated > 0) {
            $records = OperationalRecord::query()
                ->whereIn('id', $ids)
                ->get();

            foreach ($records as $record) {
                $this->syncLegacyLeadFromOperationalRecord($record, $data);
                $this->syncOperationalRelations($record);
            }
        }

        return $updated;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function logInteraction(OperationalRecord $record, array $payload, ?int $actorId = null): RecordInteraction
    {
        $channel = $this->normalizeTextEnum($payload['channel'] ?? 'manual', 24) ?? 'manual';
        $direction = $this->normalizeTextEnum($payload['direction'] ?? 'outbound', 16) ?? 'outbound';
        $status = $this->normalizeTextEnum($payload['status'] ?? 'new', 32) ?? 'new';

        $interaction = null;

        DB::transaction(function () use ($record, $payload, $actorId, $channel, $direction, $status, &$interaction): void {
            $occurredAt = $this->parseDateTime($payload['occurred_at'] ?? null) ?? now();
            $subject = trim((string) ($payload['subject'] ?? ''));
            $message = trim((string) ($payload['message'] ?? ''));

            $interaction = RecordInteraction::query()->create([
                'tenant_uuid' => (string) $record->tenant_uuid,
                'operational_record_id' => (int) $record->id,
                'lead_id' => (int) ($record->legacy_lead_id ?? 0) ?: null,
                'lead_source_id' => (int) ($record->lead_source_id ?? 0) ?: null,
                'channel' => $channel,
                'direction' => $direction,
                'status' => $status,
                'subject' => $subject !== '' ? Str::limit($subject, 190, '') : null,
                'message' => $message !== '' ? $message : null,
                'payload_json' => is_array($payload['payload_json'] ?? null) ? $payload['payload_json'] : null,
                'result_json' => is_array($payload['result_json'] ?? null) ? $payload['result_json'] : null,
                'external_ref' => filled($payload['external_ref'] ?? null)
                    ? Str::limit((string) $payload['external_ref'], 160, '')
                    : null,
                'created_by' => $actorId,
                'occurred_at' => $occurredAt,
            ]);

            $recordUpdate = [
                'last_interaction_at' => $occurredAt,
                'updated_at' => now(),
            ];
            if (array_key_exists('next_action_at', $payload)) {
                $recordUpdate['next_action_at'] = $this->parseDateTime($payload['next_action_at']);
            }
            OperationalRecord::query()
                ->where('id', $record->id)
                ->update($recordUpdate);
        });

        $freshRecord = $record->fresh();
        $this->syncLegacyLeadFromOperationalRecord($freshRecord, [
            'next_action_at' => $freshRecord->next_action_at,
        ]);
        $this->syncOperationalRelations($freshRecord);

        return $interaction;
    }

    public function deleteRecord(OperationalRecord $record, ?int $actorId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($record, $actorId, $reason): void {
            $this->logAuditInteraction($record, 'delete', $actorId, [
                'reason' => filled($reason) ? Str::limit((string) $reason, 190, '') : null,
            ]);

            $record->delete();
        });
    }

    /**
     * @param array<int,int|string> $ids
     */
    public function bulkDelete(array $ids, ?int $actorId = null, ?string $reason = null): int
    {
        $idList = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
        if (!$idList) {
            return 0;
        }

        $records = OperationalRecord::query()->whereIn('id', $idList)->get();
        foreach ($records as $record) {
            $this->deleteRecord($record, $actorId, $reason);
        }

        return $records->count();
    }

    private function syncLegacyLeadFromOperationalRecord(OperationalRecord $record, array $input): void
    {
        $legacyLeadId = (int) ($record->legacy_lead_id ?? 0);
        if ($legacyLeadId <= 0) {
            return;
        }

        $lead = LeadNormalized::query()->find($legacyLeadId);
        if (!$lead) {
            return;
        }

        $payload = [];
        if (array_key_exists('entity_type', $input)) {
            $payload['entity_type'] = $record->entity_type;
        }
        if (array_key_exists('lifecycle_stage', $input)) {
            $payload['lifecycle_stage'] = $record->lifecycle_stage;
        }
        if (array_key_exists('name', $input)) {
            $payload['name'] = $record->display_name;
        }
        if (array_key_exists('email', $input)) {
            $payload['email'] = $record->primary_email;
        }
        if (array_key_exists('phone_e164', $input)) {
            $payload['phone_e164'] = $record->primary_phone_e164;
        }
        if (array_key_exists('whatsapp_e164', $input)) {
            $payload['whatsapp_e164'] = $record->primary_whatsapp_e164;
        }
        if (array_key_exists('city', $input)) {
            $payload['city'] = $record->city;
        }
        if (array_key_exists('uf', $input)) {
            $payload['uf'] = $record->uf;
        }
        if (array_key_exists('sex', $input)) {
            $payload['sex'] = $record->sex;
        }
        if (array_key_exists('score', $input)) {
            $payload['score'] = $record->score;
        }
        if (array_key_exists('optin_email', $input)) {
            $payload['optin_email'] = $record->consent_email;
        }
        if (array_key_exists('optin_sms', $input)) {
            $payload['optin_sms'] = $record->consent_sms;
        }
        if (array_key_exists('optin_whatsapp', $input)) {
            $payload['optin_whatsapp'] = $record->consent_whatsapp;
        }
        if (array_key_exists('consent_source', $input)) {
            $payload['consent_source'] = $record->consent_source;
        }
        if (array_key_exists('consent_at', $input)) {
            $payload['consent_at'] = $record->consent_at;
        }
        if (array_key_exists('next_action_at', $input)) {
            $payload['next_action_at'] = $record->next_action_at;
        }
        if (array_key_exists('extras_json', $input)) {
            $payload['extras_json'] = $record->metadata_json;
        }

        if (!$payload) {
            return;
        }

        $lead->fill($payload);
        $lead->save();
    }

    private function syncOperationalRelations(OperationalRecord $record): void
    {
        $legacyLeadId = (int) ($record->legacy_lead_id ?? 0);
        if ($legacyLeadId > 0) {
            $this->catalogSync->syncLead($legacyLeadId);
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function logAuditInteraction(OperationalRecord $record, string $action, ?int $actorId, array $payload = []): void
    {
        RecordInteraction::query()->create([
            'tenant_uuid' => (string) $record->tenant_uuid,
            'operational_record_id' => (int) $record->id,
            'lead_id' => (int) ($record->legacy_lead_id ?? 0) ?: null,
            'lead_source_id' => (int) ($record->lead_source_id ?? 0) ?: null,
            'channel' => 'system',
            'direction' => 'internal',
            'status' => $action,
            'subject' => "operational_record_{$action}",
            'message' => null,
            'payload_json' => $payload ?: null,
            'result_json' => null,
            'external_ref' => null,
            'created_by' => $actorId,
            'occurred_at' => now(),
        ]);
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

        return $plus ? ('+' . $digits) : $digits;
    }

    private function normalizeTextEnum(mixed $value, int $maxLen): ?string
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

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
