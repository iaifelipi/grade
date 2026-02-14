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

                $limitRaw = (int) ($context['limit'] ?? $audience['limit'] ?? 500);
                $unlimited = $limitRaw <= 0;
                $limit = $unlimited ? null : max(1, $limitRaw);

                $chunkSize = (int) (config('automation_dispatch.run.chunk_size', 500) ?: 500);
                $chunkSize = max(50, min(2000, $chunkSize));

                // Best-effort scheduled_count without forcing COUNT() on big filtered audiences.
                $scheduledCount = null;
                if (!$unlimited && $limit !== null) {
                    $scheduledCount = (int) $limit * (int) $steps->count();
                } elseif (is_array($audience) && filled($audience['ids'] ?? null) && is_array($audience['ids'])) {
                    $scheduledCount = (int) count($audience['ids']) * (int) $steps->count();
                }

                if ($scheduledCount !== null) {
                    DB::table('automation_runs')
                        ->where('id', $runId)
                        ->update([
                            'scheduled_count' => $scheduledCount,
                            'updated_at' => now(),
                        ]);
                }

                $processedLeads = 0;

                $query
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
                    ->chunkById($chunkSize, function ($leads) use (
                        $tenantUuid,
                        $runId,
                        $run,
                        $flow,
                        $steps,
                        &$touchedLeadIds,
                        &$processedLeads,
                        $limit,
                        $unlimited
                    ) {
                        if ($this->shouldCancelRun($runId, $tenantUuid)) {
                            return false;
                        }

                        if (!$unlimited && $limit !== null) {
                            $remaining = $limit - $processedLeads;
                            if ($remaining <= 0) {
                                return false;
                            }
                            if ($leads->count() > $remaining) {
                                $leads = $leads->take($remaining);
                            }
                        }

                        $leadIds = $leads->pluck('id')->map(fn ($v) => (int) $v)->all();
                        $recordMap = $leadIds
                            ? DB::table('operational_records')
                                ->where('tenant_uuid', $tenantUuid)
                                ->whereIn('legacy_lead_id', $leadIds)
                                ->pluck('id', 'legacy_lead_id')
                            : collect();

                        foreach ($leads as $lead) {
                            foreach ($steps as $step) {
                                if ($this->shouldCancelRun($runId, $tenantUuid)) {
                                    return false;
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
                            $processedLeads++;
                        }

                        return true;
                    }, 'id');

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
        if (filled($filter['ids'] ?? null)) {
            $ids = $filter['ids'];
            if (is_string($ids)) {
                $ids = array_filter(array_map('intval', preg_split('/\\s*,\\s*/', $ids) ?: []));
            }
            if (is_array($ids)) {
                $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
                if ($ids) {
                    $query->whereIn('id', $ids);
                }
            }
        }

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

        // Explore filters: match Explore UI without materializing IDs in the browser.
        if (filled($filter['explore_excluded_ids'] ?? null)) {
            $ids = $filter['explore_excluded_ids'];
            if (is_string($ids)) {
                $ids = array_filter(array_map('intval', preg_split('/\\s*,\\s*/', $ids) ?: []));
            }
            if (is_array($ids)) {
                $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
                if ($ids) {
                    $query->whereNotIn('id', $ids);
                }
            }
        }

        if (filled($filter['explore_source_id'] ?? null)) {
            $query->where('lead_source_id', (int) $filter['explore_source_id']);
        }

        if (filled($filter['explore_min_score'] ?? null)) {
            $minScore = (int) $filter['explore_min_score'];
            if ($minScore > 0) {
                $query->where('score', '>=', $minScore);
            }
        }

        if (filled($filter['explore_cities'] ?? null) && is_array($filter['explore_cities'])) {
            $cities = array_values(array_filter(array_map('strval', $filter['explore_cities'])));
            if ($cities) {
                $query->whereIn('city', $cities);
            }
        }

        if (filled($filter['explore_states'] ?? null) && is_array($filter['explore_states'])) {
            $states = array_values(array_filter(array_map('strval', $filter['explore_states'])));
            if ($states) {
                $query->whereIn('uf', $states);
            }
        }

        if (filled($filter['explore_q'] ?? null)) {
            $q = trim((string) $filter['explore_q']);
            if ($q !== '') {
                $safe = addcslashes($q, '%_');
                $like = "%{$safe}%";
                $query->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('cpf', 'like', $like)
                        ->orWhere('phone_e164', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('uf', 'like', $like)
                        ->orWhere('extras_json', 'like', $like);
                });
            }
        }

        $exploreSegmentId = (int) ($filter['explore_segment_id'] ?? 0);
        $exploreNicheId = (int) ($filter['explore_niche_id'] ?? 0);
        $exploreOriginId = (int) ($filter['explore_origin_id'] ?? 0);
        if ($exploreSegmentId > 0 || $exploreNicheId > 0 || $exploreOriginId > 0) {
            // Restrict by semantics of the source (lead_source_semantics) to match Explore filtering.
            $tenantUuid = (string) ($filter['explore_tenant_uuid'] ?? '');
            if ($tenantUuid !== '') {
                $segmentIds = $exploreSegmentId > 0 ? [$exploreSegmentId] : [];
                $nicheIds = $exploreNicheId > 0 ? [$exploreNicheId] : [];
                $query->whereIn('lead_source_id', function ($q) use ($tenantUuid, $segmentIds, $nicheIds, $exploreOriginId) {
                    $q->select('lead_source_id')
                        ->from('lead_source_semantics')
                        ->where('tenant_uuid', $tenantUuid);

                    if ($segmentIds) {
                        $q->where(function ($sq) use ($segmentIds) {
                            $sq->whereIn('segment_id', $segmentIds)
                                ->orWhereExists(function ($sub) use ($segmentIds) {
                                    $sub->selectRaw('1')
                                        ->from('semantic_locations as sl_segment')
                                        ->whereColumn('sl_segment.lead_source_semantic_id', 'lead_source_semantics.id')
                                        ->where('sl_segment.type', 'segment')
                                        ->whereIn('sl_segment.ref_id', $segmentIds);
                                });
                        });
                    }

                    if ($nicheIds) {
                        $q->where(function ($sq) use ($nicheIds) {
                            $sq->whereIn('niche_id', $nicheIds)
                                ->orWhereExists(function ($sub) use ($nicheIds) {
                                    $sub->selectRaw('1')
                                        ->from('semantic_locations as sl_niche')
                                        ->whereColumn('sl_niche.lead_source_semantic_id', 'lead_source_semantics.id')
                                        ->where('sl_niche.type', 'niche')
                                        ->whereIn('sl_niche.ref_id', $nicheIds);
                                });
                        });
                    }

                    if ($exploreOriginId > 0) {
                        $q->where('origin_id', $exploreOriginId);
                    }
                });
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

        $config = $this->decodeJson($step->config_json) ?? [];
        $ignoreOptin = is_array($config) && !empty($config['ignore_optin']);

        if (in_array($stepType, ['wait', 'delay'], true)) {
            return ['skipped', null, ['mode' => 'wait', 'message' => 'Step de espera não executa envio.'], null];
        }

        if ($channel !== '') {
            if ($channel === 'email' && ((!$ignoreOptin && !$lead->optin_email) || !filled($lead->email))) {
                return ['skipped', $ignoreOptin ? 'Lead sem email válido.' : 'Lead sem email válido com opt-in.', ['mode' => 'validation'], null];
            }
            if ($channel === 'sms' && ((!$ignoreOptin && !$lead->optin_sms) || !filled($lead->phone_e164))) {
                return ['skipped', $ignoreOptin ? 'Lead sem telefone SMS.' : 'Lead sem telefone SMS com opt-in.', ['mode' => 'validation'], null];
            }
            // WhatsApp can use whatsapp_e164 when available, otherwise fallback to phone_e164.
            $hasWhatsappTarget = filled($lead->whatsapp_e164) || filled($lead->phone_e164);
            if ($channel === 'whatsapp' && ((!$ignoreOptin && !$lead->optin_whatsapp) || !$hasWhatsappTarget)) {
                return ['skipped', $ignoreOptin ? 'Lead sem telefone para WhatsApp.' : 'Lead sem WhatsApp com opt-in.', ['mode' => 'validation'], null];
            }
        }

        if ($channel === '') {
            return ['success', null, ['mode' => 'noop_step'], null];
        }

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
