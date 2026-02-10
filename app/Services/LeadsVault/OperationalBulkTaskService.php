<?php

namespace App\Services\LeadsVault;

use App\Jobs\ExecuteOperationalBulkTaskJob;
use App\Models\OperationalBulkTask;
use App\Models\OperationalBulkTaskItem;
use App\Models\OperationalRecord;
use App\Support\LeadsVault\BulkTaskPayloadValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationalBulkTaskService
{
    public function __construct(
        private readonly OperationalRecordService $recordService,
        private readonly BulkTaskPayloadValidator $payloadValidator,
    )
    {
    }

    public function createTask(
        string $scopeType,
        array $scopePayload,
        string $actionType,
        array $actionPayload,
        ?int $createdBy
    ): OperationalBulkTask {
        $this->payloadValidator->assertValid($scopeType, $scopePayload, $actionType, $actionPayload);

        $count = $this->countScopeRecords($scopeType, $scopePayload);

        $task = OperationalBulkTask::query()->create([
            'task_uuid' => (string) Str::uuid(),
            'status' => 'queued',
            'scope_type' => $scopeType,
            'action_type' => $actionType,
            'scope_json' => $scopePayload,
            'action_json' => $actionPayload,
            'total_items' => $count,
            'created_by' => $createdBy,
        ]);

        ExecuteOperationalBulkTaskJob::dispatch((int) $task->id, (string) $task->tenant_uuid)
            ->onQueue('automation');

        return $task;
    }

    public function executeTask(int $taskId, string $tenantUuid): void
    {
        app()->instance('tenant_uuid', $tenantUuid);

        /** @var OperationalBulkTask|null $task */
        $task = DB::transaction(function () use ($taskId): ?OperationalBulkTask {
            $locked = OperationalBulkTask::query()
                ->where('id', $taskId)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return null;
            }

            if ($locked->status === 'cancel_requested') {
                $locked->status = 'cancelled';
                $locked->finished_at = now();
                $locked->updated_at = now();
                $locked->save();
                return null;
            }

            // Idempotency guard: only queued tasks can be started.
            if ($locked->status !== 'queued') {
                return null;
            }

            $locked->status = 'running';
            $locked->started_at = $locked->started_at ?: now();
            $locked->last_error = null;
            $locked->save();

            return $locked;
        });

        if (!$task) {
            return;
        }

        $processed = (int) $task->processed_items;
        $success = (int) $task->success_items;
        $failed = (int) $task->failed_items;
        $sampleErrors = [];

        try {
            $scope = is_array($task->scope_json) ? $task->scope_json : [];
            $action = is_array($task->action_json) ? $task->action_json : [];

            $query = $this->buildScopeQuery((string) $task->scope_type, $scope)->orderBy('id');
            $query->chunkById(200, function ($records) use (
                $task,
                &$processed,
                &$success,
                &$failed,
                &$sampleErrors,
                $action
            ): void {
                $freshTask = OperationalBulkTask::query()->find((int) $task->id);
                if (!$freshTask || $freshTask->status === 'cancel_requested') {
                    throw new \RuntimeException('TASK_CANCELLED');
                }

                foreach ($records as $record) {
                    $processed++;
                    try {
                        $result = $this->applyAction($record, (string) $task->action_type, $action);
                        $success++;

                        OperationalBulkTaskItem::query()->updateOrCreate(
                            [
                                'task_id' => (int) $task->id,
                                'operational_record_id' => (int) $record->id,
                            ],
                            [
                                'status' => 'success',
                                'error_message' => null,
                                'result_json' => $result,
                                'processed_at' => now(),
                            ]
                        );
                    } catch (\Throwable $e) {
                        $failed++;
                        if (count($sampleErrors) < 20) {
                            $sampleErrors[] = [
                                'record_id' => (int) $record->id,
                                'error' => $e->getMessage(),
                            ];
                        }

                        OperationalBulkTaskItem::query()->updateOrCreate(
                            [
                                'task_id' => (int) $task->id,
                                'operational_record_id' => (int) $record->id,
                            ],
                            [
                                'status' => 'failed',
                                'error_message' => Str::limit($e->getMessage(), 1000, ''),
                                'result_json' => null,
                                'processed_at' => now(),
                            ]
                        );
                    }
                }

                OperationalBulkTask::query()
                    ->where('id', (int) $task->id)
                    ->update([
                        'processed_items' => $processed,
                        'success_items' => $success,
                        'failed_items' => $failed,
                        'updated_at' => now(),
                    ]);
            });

            OperationalBulkTask::query()
                ->where('id', (int) $task->id)
                ->update([
                    'status' => $failed > 0 ? 'done_with_errors' : 'done',
                    'processed_items' => $processed,
                    'success_items' => $success,
                    'failed_items' => $failed,
                    'finished_at' => now(),
                    'summary_json' => [
                        'sample_errors' => $sampleErrors,
                    ],
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            $isCancelled = $e->getMessage() === 'TASK_CANCELLED';
            OperationalBulkTask::query()
                ->where('id', (int) $task->id)
                ->update([
                    'status' => $isCancelled ? 'cancelled' : 'failed',
                    'processed_items' => $processed,
                    'success_items' => $success,
                    'failed_items' => $failed,
                    'finished_at' => now(),
                    'last_error' => $isCancelled ? null : Str::limit($e->getMessage(), 2000, ''),
                    'summary_json' => [
                        'sample_errors' => $sampleErrors,
                    ],
                    'updated_at' => now(),
                ]);

            if (!$isCancelled) {
                throw $e;
            }
        }
    }

    public function requestCancel(OperationalBulkTask $task): void
    {
        if (in_array($task->status, ['done', 'done_with_errors', 'cancelled', 'failed'], true)) {
            return;
        }

        $task->status = 'cancel_requested';
        $task->save();
    }

    public function countScopeRecords(string $scopeType, array $scopePayload): int
    {
        return $this->buildScopeQuery($scopeType, $scopePayload)->count();
    }

    private function buildScopeQuery(string $scopeType, array $scopePayload): Builder
    {
        $query = OperationalRecord::query();

        if ($scopeType === 'selected_ids') {
            $ids = collect((array) ($scopePayload['ids'] ?? []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            if (!$ids) {
                $query->whereRaw('1=0');
                return $query;
            }

            $query->whereIn('id', $ids);
            return $query;
        }

        $filters = is_array($scopePayload['filters'] ?? null) ? $scopePayload['filters'] : [];
        $query = $this->applyFilterScope($query, $filters);

        return $query;
    }

    private function applyFilterScope(Builder $query, array $data): Builder
    {
        if (!empty($data['q'])) {
            $q = trim((string) $data['q']);
            $query->where(function ($sub) use ($q): void {
                $sub->where('display_name', 'like', "%{$q}%")
                    ->orWhere('primary_email', 'like', "%{$q}%")
                    ->orWhere('document_number', 'like', "%{$q}%")
                    ->orWhere('primary_phone_e164', 'like', "%{$q}%")
                    ->orWhere('primary_whatsapp_e164', 'like', "%{$q}%");
            });
        }

        foreach (['entity_type', 'lifecycle_stage'] as $field) {
            if (!empty($data[$field])) {
                $query->where($field, strtolower((string) $data[$field]));
            }
        }

        if (!empty($data['source_id'])) {
            $query->where('lead_source_id', (int) $data['source_id']);
        }

        if (
            !empty($data['channel_type']) ||
            !empty($data['channel_value']) ||
            array_key_exists('channel_is_primary', $data) ||
            array_key_exists('channel_can_contact', $data)
        ) {
            $query->whereHas('channels', function ($sub) use ($data): void {
                if (!empty($data['channel_type'])) {
                    $sub->where('channel_type', (string) $data['channel_type']);
                }
                if (!empty($data['channel_value'])) {
                    $value = trim((string) $data['channel_value']);
                    $sub->where(function ($q) use ($value): void {
                        $q->where('value', 'like', "%{$value}%")
                            ->orWhere('value_normalized', 'like', "%{$value}%");
                    });
                }
                if (array_key_exists('channel_is_primary', $data)) {
                    $sub->where('is_primary', (bool) ((int) $data['channel_is_primary']));
                }
                if (array_key_exists('channel_can_contact', $data)) {
                    $sub->where('can_contact', (bool) ((int) $data['channel_can_contact']));
                }
            });
        }

        if (!empty($data['consent_status']) || !empty($data['consent_channel']) || !empty($data['consent_purpose'])) {
            $query->whereHas('consents', function ($sub) use ($data): void {
                if (!empty($data['consent_status'])) {
                    $sub->where('status', (string) $data['consent_status']);
                }
                if (!empty($data['consent_channel'])) {
                    $sub->where('channel_type', (string) $data['consent_channel']);
                }
                if (!empty($data['consent_purpose'])) {
                    $sub->where('purpose', strtolower(trim((string) $data['consent_purpose'])));
                }
            });
        }

        return $query;
    }

    /**
     * @return array<string,mixed>
     */
    private function applyAction(OperationalRecord $record, string $actionType, array $actionPayload): array
    {
        return match ($actionType) {
            'update_fields' => $this->applyUpdateFields($record, $actionPayload),
            'set_next_action' => $this->applySetNextAction($record, $actionPayload),
            'set_consent' => $this->applySetConsent($record, $actionPayload),
            default => throw new \InvalidArgumentException("Ação em lote não suportada: {$actionType}"),
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function applyUpdateFields(OperationalRecord $record, array $actionPayload): array
    {
        $updates = is_array($actionPayload['updates'] ?? null) ? $actionPayload['updates'] : [];
        if (!$updates) {
            throw new \InvalidArgumentException('Updates vazio para update_fields.');
        }

        $updated = $this->recordService->updateRecord($record, $updates);

        return [
            'record_id' => (int) $updated->id,
            'action' => 'update_fields',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function applySetNextAction(OperationalRecord $record, array $actionPayload): array
    {
        $days = (int) ($actionPayload['days'] ?? 0);
        if ($days < 0) {
            throw new \InvalidArgumentException('days deve ser >= 0.');
        }

        $nextActionAt = now()->addDays($days)->toISOString();
        $updated = $this->recordService->updateRecord($record, [
            'next_action_at' => $nextActionAt,
        ]);

        return [
            'record_id' => (int) $updated->id,
            'action' => 'set_next_action',
            'next_action_at' => optional($updated->next_action_at)?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function applySetConsent(OperationalRecord $record, array $actionPayload): array
    {
        $channel = Str::lower((string) ($actionPayload['channel'] ?? ''));
        $status = Str::lower((string) ($actionPayload['status'] ?? ''));
        $source = trim((string) ($actionPayload['source'] ?? ''));

        if (!in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
            throw new \InvalidArgumentException('Canal inválido para set_consent.');
        }
        if (!in_array($status, ['granted', 'revoked'], true)) {
            throw new \InvalidArgumentException('Status inválido para set_consent.');
        }

        $updates = [
            'consent_source' => $source !== '' ? $source : null,
            'consent_at' => now()->toISOString(),
        ];
        $updates["optin_{$channel}"] = $status === 'granted';

        $updated = $this->recordService->updateRecord($record, $updates);

        return [
            'record_id' => (int) $updated->id,
            'action' => 'set_consent',
            'channel' => $channel,
            'status' => $status,
        ];
    }
}
