<?php

namespace App\Support\LeadsVault;

use InvalidArgumentException;

class BulkTaskPayloadValidator
{
    public function assertValid(string $scopeType, array $scopePayload, string $actionType, array $actionPayload): void
    {
        if (!in_array($scopeType, ['selected_ids', 'filtered'], true)) {
            throw new InvalidArgumentException('scope_type inválido.');
        }

        if ($scopeType === 'selected_ids') {
            $ids = array_values(array_filter(array_map('intval', (array) ($scopePayload['ids'] ?? []))));
            if (!$ids) {
                throw new InvalidArgumentException('Selecione ao menos um id para selected_ids.');
            }
        }

        if (!in_array($actionType, ['update_fields', 'set_next_action', 'set_consent'], true)) {
            throw new InvalidArgumentException('action_type inválido.');
        }

        if ($actionType === 'update_fields') {
            $updates = $actionPayload['updates'] ?? null;
            if (!is_array($updates) || count($updates) < 1) {
                throw new InvalidArgumentException('action.updates é obrigatório para update_fields.');
            }
        }

        if ($actionType === 'set_next_action') {
            $days = $actionPayload['days'] ?? null;
            if (!is_numeric($days) || (int) $days < 0) {
                throw new InvalidArgumentException('action.days inválido para set_next_action.');
            }
        }

        if ($actionType === 'set_consent') {
            $channel = strtolower(trim((string) ($actionPayload['channel'] ?? '')));
            $status = strtolower(trim((string) ($actionPayload['status'] ?? '')));
            if (!in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
                throw new InvalidArgumentException('action.channel inválido para set_consent.');
            }
            if (!in_array($status, ['granted', 'revoked'], true)) {
                throw new InvalidArgumentException('action.status inválido para set_consent.');
            }
        }
    }
}
