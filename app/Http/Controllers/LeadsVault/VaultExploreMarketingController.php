<?php

namespace App\Http\Controllers\LeadsVault;

use App\Http\Controllers\Controller;
use App\Models\AutomationFlow;
use App\Models\AutomationFlowStep;
use App\Services\LeadsVault\AutomationExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VaultExploreMarketingController extends Controller
{
    public function __construct(private readonly AutomationExecutionService $execution)
    {
    }

    public function availability(Request $request): JsonResponse
    {
        $tenantUuid = app()->bound('tenant_uuid') ? (string) app('tenant_uuid') : '';
        if ($tenantUuid === '') {
            abort(403, 'Tenant não definido.');
        }

        $mode = Str::lower(trim((string) $request->input('selection_mode', '')));
        if ($mode === '') {
            $mode = $request->has('filters') ? 'all_matching' : 'manual';
        }
        if (!in_array($mode, ['manual', 'all_matching'], true)) {
            abort(422, 'selection_mode inválido.');
        }

        $data = $request->validate([
            'selection_mode' => ['nullable', 'string', 'in:manual,all_matching'],
            'lead_ids' => [$mode === 'manual' ? 'required' : 'nullable', 'array', 'min:1', 'max:5000'],
            'lead_ids.*' => ['integer', 'min:1'],
            'filters' => [$mode === 'all_matching' ? 'required' : 'nullable', 'array'],
            'excluded_ids' => ['nullable', 'array', 'max:5000'],
            'excluded_ids.*' => ['integer', 'min:1'],
        ]);

        $query = $this->buildSelectionQuery(
            tenantUuid: $tenantUuid,
            mode: $mode,
            leadIds: Arr::get($data, 'lead_ids', []),
            filters: Arr::get($data, 'filters', []),
            excludedIds: Arr::get($data, 'excluded_ids', []),
        );

        $hasEmail = (clone $query)->whereNotNull('email')->exists();
        $hasPhone = (clone $query)->whereNotNull('phone_e164')->exists();

        return response()->json([
            'ok' => true,
            'selection_mode' => $mode,
            'channels' => [
                'email' => ['available' => (bool) $hasEmail, 'reason' => $hasEmail ? null : 'Sem e-mail nos selecionados.'],
                'sms' => ['available' => (bool) $hasPhone, 'reason' => $hasPhone ? null : 'Sem telefone nos selecionados.'],
                'whatsapp' => ['available' => (bool) $hasPhone, 'reason' => $hasPhone ? null : 'Sem telefone nos selecionados.'],
            ],
        ]);
    }

    public function dispatch(Request $request): JsonResponse
    {
        $tenantUuid = app()->bound('tenant_uuid') ? (string) app('tenant_uuid') : '';
        if ($tenantUuid === '') {
            abort(403, 'Tenant não definido.');
        }

        $mode = Str::lower(trim((string) $request->input('selection_mode', '')));
        if ($mode === '') {
            $mode = $request->has('filters') ? 'all_matching' : 'manual';
        }
        if (!in_array($mode, ['manual', 'all_matching'], true)) {
            abort(422, 'selection_mode inválido.');
        }

        $data = $request->validate([
            'selection_mode' => ['nullable', 'string', 'in:manual,all_matching'],
            'lead_ids' => [$mode === 'manual' ? 'required' : 'nullable', 'array', 'min:1', 'max:5000'],
            'lead_ids.*' => ['integer', 'min:1'],
            'filters' => [$mode === 'all_matching' ? 'required' : 'nullable', 'array'],
            'excluded_ids' => ['nullable', 'array', 'max:5000'],
            'excluded_ids.*' => ['integer', 'min:1'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:email,sms,whatsapp'],

            'email_from_name' => ['nullable', 'string', 'max:120'],
            'email_from_email' => ['nullable', 'string', 'max:190'],
            'email_reply_to' => ['nullable', 'string', 'max:190'],
            'email_format' => ['nullable', 'string', 'in:text,html'],
            'email_subject_variants' => ['nullable', 'array', 'max:6'],
            'email_subject_variants.*' => ['string', 'max:190'],
            'email_message_variants' => ['nullable', 'array', 'max:10'],
            'email_message_variants.*' => ['string', 'max:4000'],

            'sms_message_variants' => ['nullable', 'array', 'max:10'],
            'sms_message_variants.*' => ['string', 'max:800'],

            'whatsapp_message_variants' => ['nullable', 'array', 'max:10'],
            'whatsapp_message_variants.*' => ['string', 'max:1200'],
        ]);

        $leadIds = [];
        $filters = [];
        $excludedIds = [];
        if ($mode === 'manual') {
            $leadIds = array_values(array_unique(array_map('intval', $data['lead_ids'] ?? [])));
            $leadIds = array_values(array_filter($leadIds, fn ($v) => $v > 0));
            if (!$leadIds) {
                abort(422, 'Selecione pelo menos um registro.');
            }
        } else {
            $filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
            $excludedIds = array_values(array_unique(array_map('intval', $data['excluded_ids'] ?? [])));
            $excludedIds = array_values(array_filter($excludedIds, fn ($v) => $v > 0));
        }

        $channels = collect($data['channels'] ?? [])
            ->map(fn ($c) => Str::lower(trim((string) $c)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!$channels) {
            abort(422, 'Selecione pelo menos um canal.');
        }

        $selectionQuery = $this->buildSelectionQuery(
            tenantUuid: $tenantUuid,
            mode: $mode,
            leadIds: $leadIds,
            filters: $filters,
            excludedIds: $excludedIds,
        );

        // Enforce minimum data availability (matches UI expectations).
        if (in_array('email', $channels, true)) {
            $hasEmail = (clone $selectionQuery)->whereNotNull('email')->exists();
            if (!$hasEmail) {
                return response()->json([
                    'ok' => false,
                    'code' => 'missing_email',
                    'message' => 'Não há e-mails nos registros selecionados.',
                ], 422);
            }
        }

        if (in_array('sms', $channels, true)) {
            $hasPhone = (clone $selectionQuery)->whereNotNull('phone_e164')->exists();
            if (!$hasPhone) {
                return response()->json([
                    'ok' => false,
                    'code' => 'missing_phone',
                    'message' => 'Não há telefones (SMS) nos registros selecionados.',
                ], 422);
            }
        }

        if (in_array('whatsapp', $channels, true)) {
            $hasPhone = (clone $selectionQuery)->whereNotNull('phone_e164')->exists();
            if (!$hasPhone) {
                return response()->json([
                    'ok' => false,
                    'code' => 'missing_phone',
                    'message' => 'Não há telefones (WhatsApp) nos registros selecionados.',
                ], 422);
            }
        }

        $nowLabel = now()->format('Y-m-d H:i');
        $audienceFilter = $mode === 'manual'
            ? ['ids' => $leadIds]
            : $this->normalizeExploreFiltersForAudience($tenantUuid, $filters, $excludedIds);

        $flow = AutomationFlow::query()->create([
            'tenant_uuid' => $tenantUuid,
            'name' => "Explore • Disparo ({$nowLabel})",
            'status' => 'active',
            'trigger_type' => 'manual',
            'trigger_config' => null,
            'audience_filter' => $audienceFilter,
            'goal_config' => null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'published_at' => now(),
        ]);

        $order = 1;
        foreach ($channels as $ch) {
            if ($ch === 'email') {
                $subjectVariants = array_values(array_filter(array_map('trim', (array) ($data['email_subject_variants'] ?? []))));
                $messageVariants = array_values(array_filter(array_map('trim', (array) ($data['email_message_variants'] ?? []))));
                if (!$messageVariants) {
                    return response()->json(['ok' => false, 'message' => 'Mensagem de e-mail é obrigatória.'], 422);
                }
                AutomationFlowStep::query()->create([
                    'tenant_uuid' => $tenantUuid,
                    'flow_id' => (int) $flow->id,
                    'step_order' => $order++,
                    'step_type' => 'dispatch_message',
                    'channel' => 'email',
                    'config_json' => [
                        'ignore_optin' => true,
                        'format' => in_array(($data['email_format'] ?? 'text'), ['text', 'html'], true) ? (string) $data['email_format'] : 'text',
                        'from_name' => trim((string) ($data['email_from_name'] ?? '')) ?: null,
                        'from_email' => trim((string) ($data['email_from_email'] ?? '')) ?: null,
                        'reply_to' => trim((string) ($data['email_reply_to'] ?? '')) ?: null,
                        'subject_variants' => $subjectVariants,
                        'message_variants' => $messageVariants,
                        'variant_seed' => (string) Str::uuid(),
                    ],
                    'is_active' => true,
                ]);
            } elseif ($ch === 'sms') {
                $messageVariants = array_values(array_filter(array_map('trim', (array) ($data['sms_message_variants'] ?? []))));
                if (!$messageVariants) {
                    return response()->json(['ok' => false, 'message' => 'Mensagem de SMS é obrigatória.'], 422);
                }
                AutomationFlowStep::query()->create([
                    'tenant_uuid' => $tenantUuid,
                    'flow_id' => (int) $flow->id,
                    'step_order' => $order++,
                    'step_type' => 'dispatch_message',
                    'channel' => 'sms',
                    'config_json' => [
                        'ignore_optin' => true,
                        'message_variants' => $messageVariants,
                        'variant_seed' => (string) Str::uuid(),
                    ],
                    'is_active' => true,
                ]);
            } elseif ($ch === 'whatsapp') {
                $messageVariants = array_values(array_filter(array_map('trim', (array) ($data['whatsapp_message_variants'] ?? []))));
                if (!$messageVariants) {
                    return response()->json(['ok' => false, 'message' => 'Mensagem de WhatsApp é obrigatória.'], 422);
                }
                AutomationFlowStep::query()->create([
                    'tenant_uuid' => $tenantUuid,
                    'flow_id' => (int) $flow->id,
                    'step_order' => $order++,
                    'step_type' => 'dispatch_message',
                    'channel' => 'whatsapp',
                    'config_json' => [
                        'ignore_optin' => true,
                        'message_variants' => $messageVariants,
                        'variant_seed' => (string) Str::uuid(),
                    ],
                    'is_active' => true,
                ]);
            }
        }

        $run = $this->execution->queueRun(
            flow: $flow,
            startedByType: 'user',
            startedById: auth()->id(),
            context: [
                'audience_filter' => $audienceFilter,
                // limit=0 means unlimited (for selection all_matching).
                'limit' => $mode === 'manual' ? count($leadIds) : 0,
                'source' => 'explore',
            ]
        );

        return response()->json([
            'ok' => true,
            'flow_id' => (int) $flow->id,
            'run_id' => (int) $run->id,
        ], 201);
    }

    private function buildSelectionQuery(string $tenantUuid, string $mode, array $leadIds, array $filters, array $excludedIds)
    {
        $query = DB::table('leads_normalized')
            ->where('tenant_uuid', $tenantUuid)
            ->select(['id', 'email', 'phone_e164']);

        if ($mode === 'manual') {
            $ids = array_values(array_unique(array_map('intval', $leadIds)));
            $ids = array_values(array_filter($ids, fn ($v) => $v > 0));
            if (!$ids) {
                $query->whereRaw('1=0');
                return $query;
            }
            $query->whereIn('id', $ids);
            return $query;
        }

        $this->applyExploreFilters($query, $tenantUuid, $filters);

        $ex = array_values(array_unique(array_map('intval', $excludedIds)));
        $ex = array_values(array_filter($ex, fn ($v) => $v > 0));
        if ($ex) {
            $query->whereNotIn('id', $ex);
        }

        return $query;
    }

    private function applyExploreFilters($query, string $tenantUuid, array $filters): void
    {
        $q = trim((string) ($filters['q'] ?? ''));
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

        $minScore = (int) ($filters['min_score'] ?? 0);
        if ($minScore > 0) {
            $query->where('score', '>=', $minScore);
        }

        $sourceId = (int) ($filters['lead_source_id'] ?? 0);
        if ($sourceId > 0) {
            $query->where('lead_source_id', $sourceId);
        }

        $segmentId = (int) ($filters['segment_id'] ?? 0);
        $nicheId = (int) ($filters['niche_id'] ?? 0);
        $originId = (int) ($filters['origin_id'] ?? 0);
        if ($segmentId > 0 || $nicheId > 0 || $originId > 0) {
            $segmentIds = $segmentId > 0 ? [$segmentId] : [];
            $nicheIds = $nicheId > 0 ? [$nicheId] : [];
            $query->whereIn('lead_source_id', function ($q) use ($tenantUuid, $segmentIds, $nicheIds, $originId) {
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

                if ($originId > 0) {
                    $q->where('origin_id', $originId);
                }
            });
        }

        $cities = array_values(array_filter(array_map('strval', (array) ($filters['cities'] ?? []))));
        if ($cities) {
            $query->whereIn('city', $cities);
        }
        $states = array_values(array_filter(array_map('strval', (array) ($filters['states'] ?? []))));
        if ($states) {
            $query->whereIn('uf', $states);
        }
    }

    private function normalizeExploreFiltersForAudience(string $tenantUuid, array $filters, array $excludedIds): array
    {
        $payload = [
            'explore_q' => trim((string) ($filters['q'] ?? '')),
            'explore_min_score' => (int) ($filters['min_score'] ?? 0),
            'explore_source_id' => (int) ($filters['lead_source_id'] ?? 0),
            'explore_segment_id' => (int) ($filters['segment_id'] ?? 0),
            'explore_niche_id' => (int) ($filters['niche_id'] ?? 0),
            'explore_origin_id' => (int) ($filters['origin_id'] ?? 0),
            'explore_cities' => array_values(array_filter(array_map('strval', (array) ($filters['cities'] ?? [])))),
            'explore_states' => array_values(array_filter(array_map('strval', (array) ($filters['states'] ?? [])))),
            'explore_excluded_ids' => array_values(array_unique(array_map('intval', $excludedIds))),
            'explore_tenant_uuid' => $tenantUuid,
        ];

        // Remove empty scalars/arrays to keep json small.
        foreach (array_keys($payload) as $k) {
            $v = $payload[$k];
            if (is_array($v) && empty($v)) {
                unset($payload[$k]);
            } elseif (!is_array($v) && ($v === '' || $v === 0)) {
                unset($payload[$k]);
            }
        }

        return $payload;
    }
}
