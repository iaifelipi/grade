<?php

namespace App\Services\LeadsVault;

use App\Jobs\ExecuteAutomationRunJob;
use App\Models\AutomationFlow;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutomationExecutionService
{
    public function __construct(
        private readonly OperationalCatalogSyncService $catalogSync,
        private readonly AutomationChannelDispatchService $dispatchService,
    ) {
    }

    public function queueRun(AutomationFlow $flow, string $startedByType, ?int $startedById, array $context = []): AutomationRun
    {
        $run = AutomationRun::query()->create([
            'tenant_uuid' => (string) $flow->tenant_uuid,
            'flow_id' => (int) $flow->id,
            'status' => 'queued',
            'started_by_type' => $startedByType,
            'started_by_id' => $startedById,
            'context_json' => $context,
        ]);

        ExecuteAutomationRunJob::dispatch((int) $run->id, (string) $flow->tenant_uuid)
            ->onQueue('automation');

        return $run;
    }

    public function executeRun(int $runId, string $tenantUuid): void
    {
        app()->instance('tenant_uuid', $tenantUuid);

        $lockSeconds = max(60, (int) config('automation_dispatch.run.lock_seconds', 3600));
        $lock = Cache::lock("automation-run:{$tenantUuid}:{$runId}", $lockSeconds);

        if (!$lock->get()) {
            return;
        }

        try {
            $run = DB::table('automation_runs')
                ->where('tenant_uuid', $tenantUuid)
                ->where('id', $runId)
                ->first();

            if (!$run) {
                return;
            }

            if (in_array((string) $run->status, ['done', 'done_with_errors', 'cancelled'], true)) {
                return;
            }

            if ((string) $run->status === 'cancel_requested') {
                $this->markRunCancelled($runId, $tenantUuid);
                return;
            }

            $flow = DB::table('automation_flows')
                ->where('tenant_uuid', $tenantUuid)
                ->where('id', $run->flow_id)
                ->first();

            if (!$flow) {
                DB::table('automation_runs')
                    ->where('id', $runId)
                    ->update([
                        'status' => 'failed',
                        'last_error' => 'Flow não encontrado.',
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);
                return;
            }

            $steps = DB::table('automation_flow_steps')
                ->where('tenant_uuid', $tenantUuid)
                ->where('flow_id', $flow->id)
                ->where('is_active', true)
                ->orderBy('step_order')
                ->get();

            if ($steps->isEmpty()) {
                DB::table('automation_runs')
                    ->where('id', $runId)
                    ->update([
                        'status' => 'failed',
                        'last_error' => 'Flow sem steps ativos.',
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);
                return;
            }

            DB::table('automation_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'running',
                    'started_at' => $run->started_at ?: now(),
                    'finished_at' => null,
                    'updated_at' => now(),
                    'last_error' => null,
                ]);

            $touchedLeadIds = [];

            try {
                $audience = $this->decodeJson($flow->audience_filter);
                $context = $this->decodeJson($run->context_json);

                $query = DB::table('leads_normalized')->where('tenant_uuid', $tenantUuid);
                $query = $this->applyAudience($query, is_array($audience) ? $audience : []);
                $query = $this->applyAudience($query, is_array($context['audience_filter'] ?? null) ? $context['audience_filter'] : []);

                $limit = (int) ($context['limit'] ?? $audience['limit'] ?? 500);
                $limit = max(1, min(5000, $limit));

                $leads = $query
                    ->select([
                        'id',
                        'lead_source_id',
                        'email',
                        'phone_e164',
                        'whatsapp_e164',
                        'optin_email',
                        'optin_sms',
                        'optin_whatsapp',
                        'entity_type',
                        'lifecycle_stage',
                        'name',
                    ])
                    ->orderBy('id')
                    ->limit($limit)
                    ->get();

                $recordMap = DB::table('operational_records')
                    ->where('tenant_uuid', $tenantUuid)
                    ->whereIn('legacy_lead_id', $leads->pluck('id')->all())
                    ->pluck('id', 'legacy_lead_id');

                DB::table('automation_runs')
                    ->where('id', $runId)
                    ->update([
                        'scheduled_count' => (int) $leads->count() * (int) $steps->count(),
                        'updated_at' => now(),
                    ]);

                foreach ($leads as $lead) {
                    foreach ($steps as $step) {
                        if ($this->shouldCancelRun($runId, $tenantUuid)) {
                            break 2;
                        }

                        $stepConfig = $this->decodeJson($step->config_json) ?? [];
                        $channel = Str::lower((string) ($step->channel ?? ''));
                        $idempotencyKey = $this->buildEventIdempotencyKey($tenantUuid, $runId, (int) $step->id, (int) $lead->id, $channel);

                        $reserved = $this->reserveEventSlot(
                            tenantUuid: $tenantUuid,
                            flowId: (int) $flow->id,
                            step: $step,
                            runId: $runId,
                            lead: $lead,
                            idempotencyKey: $idempotencyKey,
                            payload: [
                                'step_order' => (int) $step->step_order,
                                'config' => is_array($stepConfig) ? $stepConfig : new \stdClass(),
                            ]
                        );

                        if ($reserved['skip']) {
                            if ($reserved['status'] === 'success') {
                                $this->ensureSuccessInteraction(
                                    tenantUuid: $tenantUuid,
                                    run: $run,
                                    flow: $flow,
                                    step: $step,
                                    lead: $lead,
                                    eventId: (int) $reserved['event_id'],
                                    externalRef: $reserved['external_ref'],
                                    response: is_array($reserved['response']) ? $reserved['response'] : ['mode' => 'existing_event'],
                                    recordMap: $recordMap,
                                    stepConfig: is_array($stepConfig) ? $stepConfig : []
                                );
                                $touchedLeadIds[(int) $lead->id] = true;
                            }
                            continue;
                        }

                        [$status, $errorMessage, $response, $externalRef] = $this->executeStep(
                            lead: $lead,
                            step: $step,
                            idempotencyKey: $idempotencyKey
                        );

                        $this->finalizeEventSlot(
                            eventId: (int) $reserved['event_id'],
                            status: $status,
                            errorMessage: $errorMessage,
                            response: $response,
                            externalRef: $status === 'success' ? $externalRef : null
                        );

                        if ($status === 'success') {
                            $this->ensureSuccessInteraction(
                                tenantUuid: $tenantUuid,
                                run: $run,
                                flow: $flow,
                                step: $step,
                                lead: $lead,
                                eventId: (int) $reserved['event_id'],
                                externalRef: $externalRef,
                                response: $response,
                                recordMap: $recordMap,
                                stepConfig: is_array($stepConfig) ? $stepConfig : []
                            );
                            $touchedLeadIds[(int) $lead->id] = true;
                        }
                    }
                }

                $stats = $this->loadRunEventStats($tenantUuid, $runId);

                if ($this->shouldCancelRun($runId, $tenantUuid)) {
                    DB::table('automation_runs')
                        ->where('id', $runId)
                        ->update([
                            'status' => 'cancelled',
                            'processed_count' => $stats['processed_count'],
                            'success_count' => $stats['success_count'],
                            'failure_count' => $stats['failure_count'],
                            'finished_at' => now(),
                            'updated_at' => now(),
                        ]);

                    if ($touchedLeadIds) {
                        $this->catalogSync->syncLeadIds(array_keys($touchedLeadIds), $tenantUuid);
                    }

                    return;
                }

                DB::table('automation_flows')
                    ->where('tenant_uuid', $tenantUuid)
                    ->where('id', $flow->id)
                    ->update([
                        'last_run_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('automation_runs')
                    ->where('id', $runId)
                    ->update([
                        'status' => $stats['failure_count'] > 0 ? 'done_with_errors' : 'done',
                        'processed_count' => $stats['processed_count'],
                        'success_count' => $stats['success_count'],
                        'failure_count' => $stats['failure_count'],
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);

                if ($touchedLeadIds) {
                    $this->catalogSync->syncLeadIds(array_keys($touchedLeadIds), $tenantUuid);
                }
            } catch (\Throwable $e) {
                $stats = $this->loadRunEventStats($tenantUuid, $runId);

                DB::table('automation_runs')
                    ->where('id', $runId)
                    ->update([
                        'status' => 'failed',
                        'processed_count' => $stats['processed_count'],
                        'success_count' => $stats['success_count'],
                        'failure_count' => max(1, $stats['failure_count']),
                        'last_error' => $e->getMessage(),
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);

                throw $e;
            }
        } finally {
            optional($lock)->release();
        }
    }

    private function applyAudience($query, array $filter)
    {
        if (filled($filter['source_id'] ?? null)) {
            $query->where('lead_source_id', (int) $filter['source_id']);
        }

        if (filled($filter['entity_type'] ?? null)) {
            $query->where('entity_type', Str::lower((string) $filter['entity_type']));
        }

        if (filled($filter['lifecycle_stage'] ?? null)) {
            $query->where('lifecycle_stage', Str::lower((string) $filter['lifecycle_stage']));
        }

        if (filled($filter['segment_id'] ?? null)) {
            $query->where('segment_id', (int) $filter['segment_id']);
        }

        if (filled($filter['niche_id'] ?? null)) {
            $query->where('niche_id', (int) $filter['niche_id']);
        }

        if (filled($filter['origin_id'] ?? null)) {
            $query->where('origin_id', (int) $filter['origin_id']);
        }

        if (filled($filter['channel_optin'] ?? null)) {
            $channel = Str::lower((string) $filter['channel_optin']);
            if ($channel === 'email') {
                $query->where('optin_email', true)->whereNotNull('email');
            } elseif ($channel === 'sms') {
                $query->where('optin_sms', true)->whereNotNull('phone_e164');
            } elseif ($channel === 'whatsapp') {
                $query->where('optin_whatsapp', true)->whereNotNull('whatsapp_e164');
            }
        }

        return $query;
    }

    /**
     * @return array{0:string,1:?string,2:array<string,mixed>,3:?string}
     */
    private function executeStep(object $lead, object $step, string $idempotencyKey): array
    {
        $stepType = Str::lower((string) ($step->step_type ?? 'action'));
        $channel = Str::lower((string) ($step->channel ?? ''));

        if (in_array($stepType, ['wait', 'delay'], true)) {
            return ['skipped', null, ['mode' => 'wait', 'message' => 'Step de espera não executa envio.'], null];
        }

        if ($channel !== '') {
            if ($channel === 'email' && (!$lead->optin_email || !filled($lead->email))) {
                return ['skipped', 'Lead sem email válido com opt-in.', ['mode' => 'validation'], null];
            }
            if ($channel === 'sms' && (!$lead->optin_sms || !filled($lead->phone_e164))) {
                return ['skipped', 'Lead sem telefone SMS com opt-in.', ['mode' => 'validation'], null];
            }
            if ($channel === 'whatsapp' && (!$lead->optin_whatsapp || !filled($lead->whatsapp_e164))) {
                return ['skipped', 'Lead sem WhatsApp com opt-in.', ['mode' => 'validation'], null];
            }
        }

        if ($channel === '') {
            return ['success', null, ['mode' => 'noop_step'], null];
        }

        $config = $this->decodeJson($step->config_json) ?? [];
        $result = $this->dispatchService->dispatch(
            channel: $channel,
            lead: $lead,
            config: is_array($config) ? $config : [],
            context: ['idempotency_key' => $idempotencyKey]
        );

        return [
            (string) ($result['status'] ?? 'failed'),
            isset($result['error']) ? (string) $result['error'] : null,
            is_array($result['response'] ?? null) ? $result['response'] : ['mode' => 'unknown'],
            isset($result['external_ref']) ? (string) $result['external_ref'] : null,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{skip:bool,event_id:int,status:?string,external_ref:?string,response:array<string,mixed>|null}
     */
    private function reserveEventSlot(
        string $tenantUuid,
        int $flowId,
        object $step,
        int $runId,
        object $lead,
        string $idempotencyKey,
        array $payload
    ): array {
        return DB::transaction(function () use ($tenantUuid, $flowId, $step, $runId, $lead, $idempotencyKey, $payload): array {
            $existing = DB::table('automation_events')
                ->where('tenant_uuid', $tenantUuid)
                ->where('run_id', $runId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing && in_array((string) $existing->status, ['success', 'skipped'], true)) {
                return [
                    'skip' => true,
                    'event_id' => (int) $existing->id,
                    'status' => (string) $existing->status,
                    'external_ref' => isset($existing->external_ref) ? (string) $existing->external_ref : null,
                    'response' => $this->decodeJson($existing->response_json),
                ];
            }

            $attempt = (int) ($existing->attempt ?? 0) + 1;
            $now = now();

            $data = [
                'tenant_uuid' => $tenantUuid,
                'flow_id' => $flowId,
                'flow_step_id' => (int) $step->id,
                'run_id' => $runId,
                'lead_id' => (int) $lead->id,
                'lead_source_id' => (int) ($lead->lead_source_id ?? 0) ?: null,
                'event_type' => (string) ($step->step_type ?? 'action'),
                'channel' => filled($step->channel ?? null) ? Str::lower((string) $step->channel) : null,
                'status' => 'processing',
                'external_ref' => null,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'attempt' => $attempt,
                'occurred_at' => $now,
                'error_message' => null,
                'idempotency_key' => $idempotencyKey,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('automation_events')
                    ->where('id', (int) $existing->id)
                    ->update($data);

                return [
                    'skip' => false,
                    'event_id' => (int) $existing->id,
                    'status' => null,
                    'external_ref' => null,
                    'response' => null,
                ];
            }

            $data['created_at'] = $now;
            $eventId = DB::table('automation_events')->insertGetId($data);

            return [
                'skip' => false,
                'event_id' => (int) $eventId,
                'status' => null,
                'external_ref' => null,
                'response' => null,
            ];
        });
    }

    /**
     * @param array<string,mixed> $response
     */
    private function finalizeEventSlot(int $eventId, string $status, ?string $errorMessage, array $response, ?string $externalRef): void
    {
        DB::table('automation_events')
            ->where('id', $eventId)
            ->update([
                'status' => $status,
                'error_message' => $errorMessage,
                'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'external_ref' => $externalRef,
                'occurred_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<int,int> $recordMap
     * @param array<string,mixed> $response
     * @param array<string,mixed> $stepConfig
     */
    private function ensureSuccessInteraction(
        string $tenantUuid,
        object $run,
        object $flow,
        object $step,
        object $lead,
        int $eventId,
        ?string $externalRef,
        array $response,
        $recordMap,
        array $stepConfig
    ): void {
        if (!filled($step->channel ?? null)) {
            return;
        }

        DB::table('record_interactions')->updateOrInsert(
            [
                'tenant_uuid' => $tenantUuid,
                'automation_event_id' => $eventId,
            ],
            [
                'operational_record_id' => (int) ($recordMap[(int) $lead->id] ?? 0) ?: null,
                'lead_id' => (int) $lead->id,
                'lead_source_id' => (int) ($lead->lead_source_id ?? 0) ?: null,
                'channel' => (string) $step->channel,
                'direction' => 'outbound',
                'status' => 'sent',
                'subject' => null,
                'message' => null,
                'payload_json' => json_encode([
                    'flow_id' => (int) $flow->id,
                    'step_id' => (int) $step->id,
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'result_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'external_ref' => $externalRef,
                'created_by' => (int) ($run->started_by_id ?? 0) ?: null,
                'occurred_at' => now(),
                'updated_at' => now(),
            ]
        );

        $leadUpdate = ['last_interaction_at' => now(), 'updated_at' => now()];
        $days = (int) ($stepConfig['next_action_in_days'] ?? 0);
        if ($days > 0) {
            $leadUpdate['next_action_at'] = now()->addDays($days);
        }

        DB::table('leads_normalized')
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $lead->id)
            ->update($leadUpdate);
    }

    /**
     * @return array{processed_count:int,success_count:int,failure_count:int}
     */
    private function loadRunEventStats(string $tenantUuid, int $runId): array
    {
        $row = DB::table('automation_events')
            ->where('tenant_uuid', $tenantUuid)
            ->where('run_id', $runId)
            ->selectRaw('COUNT(*) as processed_count')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failure_count")
            ->first();

        return [
            'processed_count' => (int) ($row->processed_count ?? 0),
            'success_count' => (int) ($row->success_count ?? 0),
            'failure_count' => (int) ($row->failure_count ?? 0),
        ];
    }

    private function buildEventIdempotencyKey(string $tenantUuid, int $runId, int $stepId, int $leadId, string $channel): string
    {
        $normalizedChannel = $channel !== '' ? $channel : '_';

        return 'ae_' . sha1(implode('|', [$tenantUuid, $runId, $stepId, $leadId, $normalizedChannel]));
    }

    private function shouldCancelRun(int $runId, string $tenantUuid): bool
    {
        $status = DB::table('automation_runs')
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $runId)
            ->value('status');

        return Str::lower((string) $status) === 'cancel_requested';
    }

    private function markRunCancelled(int $runId, string $tenantUuid): void
    {
        $stats = $this->loadRunEventStats($tenantUuid, $runId);

        DB::table('automation_runs')
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $runId)
            ->update([
                'status' => 'cancelled',
                'processed_count' => $stats['processed_count'],
                'success_count' => $stats['success_count'],
                'failure_count' => $stats['failure_count'],
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
