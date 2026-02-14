<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadNormalized;
use App\Models\LeadSource;
use App\Models\LeadSourceSemantic;
use App\Models\Tenant;
use App\Services\GuestAuditService;
use App\Services\LeadsVault\LeadSourceCancelService;
use App\Services\LeadsVault\LeadSourceReprocessService;
use App\Services\LeadsVault\SubscriberUpdateService;
use App\Support\LeadsVault\StandardColumnsSchema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerImportFileAdminController extends Controller
{
    private const DYNAMIC_EXTRA_COLUMNS_CACHE_MINUTES = 5;

    public function index(Request $request): View
    {
        $perf = $this->startPerformanceProbe('index');
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $tenantUuid = $this->resolveTenantUuid($request, $isGlobalSuper);
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));
        $archivedMode = trim((string) $request->query('archived', ''));

        $scopeQuery = $this->baseScopeQuery($isGlobalSuper, $tenantUuid);
        $scopeQuery = $this->applySearch($scopeQuery, $search);
        $scopeQuery = $this->applyArchivedMode($scopeQuery, $archivedMode);

        $listQuery = clone $scopeQuery;
        if ($status !== '') {
            $listQuery->where('status', $status);
        }

        $files = $listQuery
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $visibleCount = (clone $scopeQuery)->count();
        $completedCount = (clone $scopeQuery)->where('status', 'done')->count();
        $processingCount = (clone $scopeQuery)
            ->whereIn('status', ['queued', 'uploading', 'importing', 'normalizing'])
            ->count();
        $failedCount = (clone $scopeQuery)->whereIn('status', ['failed', 'canceled', 'cancelled'])->count();

        $tenants = $isGlobalSuper
            ? Tenant::query()->orderBy('name')->get(['uuid', 'slug', 'name'])
            : collect();

        $statusOptions = [
            'queued' => 'queued',
            'uploading' => 'uploading',
            'importing' => 'importing',
            'normalizing' => 'normalizing',
            'done' => 'done',
            'failed' => 'failed',
            'canceled' => 'canceled',
            'cancelled' => 'cancelled',
        ];

        $viewData = [
            'files' => $files,
            'isGlobalSuper' => $isGlobalSuper,
            'tenants' => $tenants,
            'selectedTenantUuid' => $isGlobalSuper ? $tenantUuid : '',
            'selectedStatus' => $status,
            'selectedArchivedMode' => $archivedMode,
            'search' => $search,
            'visibleCount' => (int) $visibleCount,
            'completedCount' => (int) $completedCount,
            'processingCount' => (int) $processingCount,
            'failedCount' => (int) $failedCount,
            'statusOptions' => $statusOptions,
        ];
        $this->finishPerformanceProbe($request, 'index', $perf, [
            'tenant_uuid' => $tenantUuid,
            'is_global_super' => $isGlobalSuper,
            'payload_items' => (int) $files->count(),
            'payload_total' => (int) $files->total(),
        ]);

        return view('admin.customers.files', $viewData);
    }

    public function cancel(
        Request $request,
        string $id,
        LeadSourceCancelService $service
    ): RedirectResponse {
        $source = $this->resolveSourceForAction($request, $id);
        $service->cancel((int) $source->id);
        $source->refresh();
        $this->forgetSubscribersDynamicExtraColumnsCache($source);
        $this->logFileEvent($request, 'cancel', $source, ['origin' => 'admin.customers.files']);

        return redirect()->back()->with('status', 'Importação cancelada com sucesso.');
    }

    public function show(Request $request, string $id): View|RedirectResponse
    {
        $perf = $this->startPerformanceProbe('overview');
        $normalizedQuery = array_filter([
            'tenant_uuid' => trim((string) $request->query('tenant_uuid', '')),
            'status' => trim((string) $request->query('status', '')),
            'archived' => trim((string) $request->query('archived', '')),
            'q' => trim((string) $request->query('q', '')),
            'page' => (int) $request->query('page', 1) > 1 ? (int) $request->query('page', 1) : null,
        ], static fn ($value): bool => $value !== null && $value !== '');
        $currentQuery = $request->query();
        ksort($normalizedQuery);
        $sortedCurrent = $currentQuery;
        ksort($sortedCurrent);
        if ($sortedCurrent !== $normalizedQuery) {
            return redirect()->route('admin.customers.files.show', array_merge(['id' => $id], $normalizedQuery), 301);
        }

        $source = $this->resolveSourceForAction($request, $id);
        $isGlobalSuper = $this->isGlobalSuperAdminContext();

        $processedRows = max(0, (int) ($source->processed_rows ?? 0));
        $totalRows = max(0, (int) ($source->total_rows ?? 0));
        $invalidRows = max(0, $totalRows - $processedRows);
        $progressPercent = $totalRows > 0 ? max(0, min(100, (int) round(($processedRows / $totalRows) * 100))) : 0;

        $status = strtolower((string) ($source->status ?? 'queued'));
        $qualityScore = $progressPercent;
        if (in_array($status, ['failed', 'canceled', 'cancelled'], true)) {
            $qualityScore = max(0, $qualityScore - 20);
        }

        $mapping = is_array($source->mapping_json) ? $source->mapping_json : [];
        $columnsCount = count(array_keys($mapping));
        $modelsCount = count(array_values(array_filter($mapping, static fn ($v): bool => trim((string) $v) !== '')));

        $semanticCount = 0;
        if (!empty($source->semantic_anchor)) {
            $semanticCount++;
        }
        if (Schema::hasTable('lead_source_semantics')) {
            $semantic = LeadSourceSemantic::withoutGlobalScopes()
                ->where('lead_source_id', (int) $source->id)
                ->first();
            if ($semantic) {
                $semanticCount += count(array_filter([
                    $semantic->segment_id,
                    $semantic->niche_id,
                    $semantic->origin_id,
                ], static fn ($v): bool => !empty($v)));
            }
        }

        $canCancel = in_array($status, ['queued', 'uploading', 'importing', 'normalizing'], true);
        $canReprocess = in_array($status, ['done', 'failed', 'canceled', 'cancelled'], true);
        $canRetryFailedRows = in_array($status, ['failed', 'canceled', 'cancelled'], true);
        $toolsCount = count(array_filter([
            true, // overview
            true, // history
            true, // error report
            true, // archive
            true, // delete
            $canReprocess,
            $canCancel,
            $canRetryFailedRows,
        ]));

        $historyRows = collect();
        if (Schema::hasTable('guest_file_events')) {
            $historyRows = DB::table('guest_file_events as e')
                ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
                ->where('e.lead_source_id', (int) $source->id)
                ->orderByDesc('e.id')
                ->limit(25)
                ->get([
                    'e.id',
                    'e.action',
                    'e.actor_type',
                    'e.user_id',
                    'e.created_at',
                    'e.payload_json',
                    'u.name as actor_name',
                    'u.email as actor_email',
                ]);
        }

        $history = $this->mapHistoryRows($historyRows->all());
        $updatesSeries = $this->buildUpdatesSeries((int) $source->id);
        $growthSeries = $this->buildListGrowthSeries((int) $source->id);

        $showTenantUuid = $isGlobalSuper
            ? trim((string) $request->query('tenant_uuid', (string) ($source->tenant_uuid ?? '')))
            : '';

        $query = array_filter([
            'tenant_uuid' => $showTenantUuid !== '' ? $showTenantUuid : null,
            'status' => trim((string) $request->query('status', '')),
            'archived' => trim((string) $request->query('archived', '')),
            'q' => trim((string) $request->query('q', '')),
            'page' => trim((string) $request->query('page', '')),
        ], static fn ($value): bool => $value !== null && $value !== '');
        $backUrl = route('admin.customers.files.index', $query);

        $viewData = [
            'file' => $source,
            'history' => $history,
            'backUrl' => $backUrl,
            'tenantUuid' => (string) ($source->tenant_uuid ?? ''),
            'isGlobalSuper' => $isGlobalSuper,
            'processedRows' => $processedRows,
            'totalRows' => $totalRows,
            'invalidRows' => $invalidRows,
            'progressPercent' => $progressPercent,
            'qualityScore' => $qualityScore,
            'cards' => [
                'records' => $totalRows,
                'semantics' => $semanticCount,
                'columns' => $columnsCount,
                'models' => $modelsCount,
                'tools' => $toolsCount,
            ],
            'updatesSeries' => $updatesSeries,
            'growthSeries' => $growthSeries,
        ];
        $this->finishPerformanceProbe($request, 'overview', $perf, [
            'tenant_uuid' => (string) ($source->tenant_uuid ?? ''),
            'source_id' => (int) $source->id,
            'payload_items' => (int) count($history),
            'processed_rows' => $processedRows,
            'total_rows' => $totalRows,
        ]);

        return view('admin.customers.file-show', $viewData);
    }

    public function subscribers(Request $request, string $id): View
    {
        $perf = $this->startPerformanceProbe('subscribers');
        $source = $this->resolveSourceForAction($request, $id);
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $tenantUuid = (string) ($source->tenant_uuid ?? '');
        $perPageAllowed = [10, 20, 30, 50, 100, 200, 500, 1000];
        $perPageRaw = (int) $request->query('per_page', 20);
        $perPage = in_array($perPageRaw, $perPageAllowed, true) ? $perPageRaw : 20;

        $query = $this->subscribersQuery($request, $source, $isGlobalSuper);
        $search = trim((string) $request->query('q', ''));
        $scoreMin = (int) $request->query('score_min', 0);

        $subscribers = $query->orderByDesc('id')->paginate($perPage)->withQueryString();
        $subscribers->setCollection(
            $subscribers->getCollection()->map(function (LeadNormalized $subscriber): LeadNormalized {
                [$standard, $filteredExtras] = $this->extractExploreStandardColumns($subscriber);
                $formattedStandard = $this->formatExploreStandardColumns($standard);
                $subscriber->setAttribute('standard_columns', $standard);
                $subscriber->setAttribute('standard_columns_formatted', $formattedStandard);
                $subscriber->setAttribute('filtered_extras_json', $filteredExtras);

                return $subscriber;
            })
        );

        $metricsBase = $isGlobalSuper
            ? LeadNormalized::withoutGlobalScopes()->where('tenant_uuid', $tenantUuid)->where('lead_source_id', (int) $source->id)
            : LeadNormalized::query()->where('lead_source_id', (int) $source->id);

        $totalSubscribers = (clone $metricsBase)->count();
        $withEmail = (clone $metricsBase)->whereNotNull('email')->where('email', '!=', '')->count();
        $withPhone = (clone $metricsBase)->whereNotNull('phone_e164')->where('phone_e164', '!=', '')->count();
        $avgScore = (int) round((float) ((clone $metricsBase)->avg('score') ?: 0));
        $dynamicExtraColumns = $this->resolveSubscribersDynamicExtraColumns($source, $isGlobalSuper);
        $listDisplayName = trim((string) ($source->display_name ?: $source->original_name ?: ''));
        if ($listDisplayName === '') {
            $listDisplayName = 'Lista sem nome';
        }
        $customerName = Tenant::withoutGlobalScopes()
            ->where('uuid', $tenantUuid)
            ->value('name');
        $customerDisplayName = trim((string) ($customerName ?: ''));
        if ($customerDisplayName === '') {
            $customerDisplayName = 'Cliente não identificado';
        }

        $viewData = [
            'file' => $source,
            'subscribers' => $subscribers,
            'isGlobalSuper' => $isGlobalSuper,
            'tenantUuid' => $tenantUuid,
            'listDisplayName' => $listDisplayName,
            'customerDisplayName' => $customerDisplayName,
            'search' => $search,
            'scoreMin' => $scoreMin > 0 ? $scoreMin : '',
            'perPage' => $perPage,
            'perPageAllowed' => $perPageAllowed,
            'totalSubscribers' => (int) $totalSubscribers,
            'withEmail' => (int) $withEmail,
            'withPhone' => (int) $withPhone,
            'avgScore' => $avgScore,
            'dynamicExtraColumns' => $dynamicExtraColumns,
        ];
        $this->finishPerformanceProbe($request, 'subscribers', $perf, [
            'tenant_uuid' => $tenantUuid,
            'source_id' => (int) $source->id,
            'payload_items' => (int) $subscribers->count(),
            'payload_total' => (int) $subscribers->total(),
            'dynamic_extra_columns' => (int) count($dynamicExtraColumns),
        ]);

        return view('admin.customers.file-subscribers', $viewData);
    }

    public function editSubscriber(Request $request, string $id, string $subscriberId): View
    {
        $source = $this->resolveSourceForAction($request, $id);
        $subscriber = $this->resolveSubscriberForSource($source, $subscriberId);
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $tenantUuid = (string) ($source->tenant_uuid ?? '');

        $dynamicExtraColumns = $this->resolveSubscribersDynamicExtraColumns($source, $isGlobalSuper);
        [$standardColumns, $extras] = $this->extractExploreStandardColumns($subscriber);
        foreach (array_keys($extras) as $extraKey) {
            $extraKey = trim((string) $extraKey);
            if ($extraKey === '') {
                continue;
            }
            if ($this->standardColumnFromKey($extraKey) !== null) {
                continue;
            }
            $col = 'extra_' . substr(md5($extraKey), 0, 10);
            $exists = collect($dynamicExtraColumns)->contains(fn (array $item): bool => $item['key'] === $extraKey);
            if ($exists) {
                continue;
            }
            $dynamicExtraColumns[] = [
                'key' => $extraKey,
                'label' => mb_convert_case(str_replace('_', ' ', $extraKey), MB_CASE_TITLE, 'UTF-8'),
                'col' => $col,
            ];
        }
        usort($dynamicExtraColumns, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        $queryContext = array_filter([
            'tenant_uuid' => $isGlobalSuper ? trim((string) $request->query('tenant_uuid', '')) : null,
            'q' => trim((string) $request->query('q', '')),
            'score_min' => trim((string) $request->query('score_min', '')),
            'per_page' => trim((string) $request->query('per_page', '')),
            'page' => trim((string) $request->query('page', '')),
        ], static fn ($v): bool => $v !== null && $v !== '');

        return view('admin.customers.subscriber-edit', [
            'file' => $source,
            'subscriber' => $subscriber,
            'isGlobalSuper' => $isGlobalSuper,
            'tenantUuid' => $tenantUuid,
            'dynamicExtraColumns' => $dynamicExtraColumns,
            'standardColumns' => $standardColumns,
            'filteredExtras' => $extras,
            'queryContext' => $queryContext,
            'backUrl' => route('admin.customers.files.subscribers', array_merge(['id' => (string) ($source->public_uid ?: $source->id)], $queryContext)),
        ]);
    }

    public function subscribersExport(Request $request, string $id): StreamedResponse
    {
        $source = $this->resolveSourceForAction($request, $id);
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $tenantUuid = (string) ($source->tenant_uuid ?? '');

        $query = $this->subscribersQuery($request, $source, $isGlobalSuper)
            ->orderByDesc('id');

        $filename = 'subscribers-file-' . (int) $source->id . '-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            if (!$out) {
                return;
            }

            fputcsv($out, ['id', 'row_num', 'name', 'email', 'phone_e164', 'city', 'uf', 'score', 'created_at', 'updated_at']);

            $query->chunkById(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        (int) $row->id,
                        (int) ($row->row_num ?? 0),
                        (string) ($row->name ?? ''),
                        (string) ($row->email ?? ''),
                        (string) ($row->phone_e164 ?? ''),
                        (string) ($row->city ?? ''),
                        (string) ($row->uf ?? ''),
                        (int) ($row->score ?? 0),
                        (string) ($row->created_at ?? ''),
                        (string) ($row->updated_at ?? ''),
                    ]);
                }
            }, 'id');

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateSubscriber(
        Request $request,
        string $id,
        string $subscriberId,
        SubscriberUpdateService $subscriberUpdateService
    ): RedirectResponse
    {
        $source = $this->resolveSourceForAction($request, $id);
        $subscriber = $this->resolveSubscriberForSource($source, $subscriberId);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'data_nascimento' => ['nullable', 'string', 'max:40'],
            'sex' => ['nullable', 'string', 'max:16'],
            'whatsapp_e164' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'max:4'],
            'entity_type' => ['nullable', 'string', 'max:32'],
            'lifecycle_stage' => ['nullable', 'string', 'max:40'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'consent_source' => ['nullable', 'string', 'max:120'],
            'optin_email' => ['nullable'],
            'optin_sms' => ['nullable'],
            'optin_whatsapp' => ['nullable'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['nullable', 'string', 'max:5000'],
        ]);

        $result = $subscriberUpdateService->updateSubscriberFromAdmin(
            $subscriber,
            $data,
            $request->boolean('optin_email'),
            $request->boolean('optin_sms'),
            $request->boolean('optin_whatsapp')
        );
        $this->forgetSubscribersDynamicExtraColumnsCache($source);
        $diff = is_array($result['diff'] ?? null) ? $result['diff'] : [];

        $this->logFileEvent($request, 'subscriber_edit', $source, [
            'origin' => 'admin.customers.files.subscribers',
            'subscriber_id' => (int) $subscriber->id,
            'changed_fields' => array_keys($diff),
            'diff' => $diff,
        ]);

        return redirect()->back()->with('status', 'Assinante atualizado com sucesso.');
    }

    public function bulkSubscribersAction(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $source = $this->resolveSourceForAction($request, $id);
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $allowedActions = [
            'subscribe',
            'unsubscribe',
            'unconfirm',
            'resend_confirmation',
            'deactivate',
            'delete',
            'blocked_ips',
        ];

        $data = $request->validate([
            'action' => ['required', Rule::in($allowedActions)],
            'scope' => ['nullable', Rule::in(['selected', 'all_filtered'])],
            'subscriber_ids' => ['nullable', 'array', 'max:5000'],
            'subscriber_ids.*' => ['nullable', 'string', 'max:64'],
        ]);

        $action = (string) $data['action'];
        $scope = (string) ($data['scope'] ?? 'selected');
        $keys = collect((array) ($data['subscriber_ids'] ?? []))
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query = $this->subscribersQuery($request, $source, $isGlobalSuper);
        if ($scope === 'selected') {
            if ($keys === []) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Nenhum assinante selecionado para ação em massa.',
                ], 422);
            }
            $query = $this->applySubscriberRouteKeysFilter($query, $keys);
        }

        $targeted = (int) (clone $query)->count();
        if ($targeted < 1) {
            return response()->json([
                'ok' => false,
                'action' => $action,
                'scope' => $scope,
                'targeted' => 0,
                'affected' => 0,
                'skipped' => 0,
                'message' => 'Nenhum assinante elegível para esta ação.',
            ], 404);
        }

        $now = now();
        $affected = 0;
        $actionLabel = [
            'subscribe' => 'Inscrever',
            'unsubscribe' => 'Desinscrever',
            'unconfirm' => 'Desconfirmar',
            'resend_confirmation' => 'Reenviar confirmação',
            'deactivate' => 'Desativar',
            'delete' => 'Excluir',
            'blocked_ips' => 'Marcar IPs bloqueados',
        ][$action] ?? $action;

        DB::transaction(function () use ($action, $query, $now, &$affected): void {
            if ($action === 'delete') {
                if ($this->hasLeadNormalizedColumn('deleted_at')) {
                    $updates = ['deleted_at' => $now];
                    if ($this->hasLeadNormalizedColumn('deleted_by')) {
                        $updates['deleted_by'] = (int) auth()->id();
                    }
                    $affected = (int) (clone $query)->update($updates);
                    return;
                }
                $affected = (int) (clone $query)->delete();
                return;
            }

            $updates = [];
            switch ($action) {
                case 'subscribe':
                    $updates['optin_email'] = true;
                    if ($this->hasLeadNormalizedColumn('lifecycle_stage')) {
                        $updates['lifecycle_stage'] = 'active';
                    }
                    if ($this->hasLeadNormalizedColumn('consent_source')) {
                        $updates['consent_source'] = 'admin_bulk_subscribe';
                    }
                    if ($this->hasLeadNormalizedColumn('consent_at')) {
                        $updates['consent_at'] = $now;
                    }
                    break;
                case 'unsubscribe':
                    $updates['optin_email'] = false;
                    if ($this->hasLeadNormalizedColumn('consent_source')) {
                        $updates['consent_source'] = 'admin_bulk_unsubscribe';
                    }
                    break;
                case 'unconfirm':
                    $updates['optin_email'] = false;
                    $updates['optin_sms'] = false;
                    $updates['optin_whatsapp'] = false;
                    if ($this->hasLeadNormalizedColumn('lifecycle_stage')) {
                        $updates['lifecycle_stage'] = 'pending_confirmation';
                    }
                    break;
                case 'resend_confirmation':
                    if ($this->hasLeadNormalizedColumn('next_action_at')) {
                        $updates['next_action_at'] = $now;
                    }
                    if ($this->hasLeadNormalizedColumn('lifecycle_stage')) {
                        $updates['lifecycle_stage'] = 'pending_confirmation';
                    }
                    if ($this->hasLeadNormalizedColumn('consent_source')) {
                        $updates['consent_source'] = 'admin_bulk_resend_confirmation';
                    }
                    break;
                case 'deactivate':
                    if ($this->hasLeadNormalizedColumn('lifecycle_stage')) {
                        $updates['lifecycle_stage'] = 'inactive';
                    }
                    break;
                case 'blocked_ips':
                    if ($this->hasLeadNormalizedColumn('lifecycle_stage')) {
                        $updates['lifecycle_stage'] = 'blocked_ip';
                    }
                    break;
            }

            if ($updates === []) {
                $affected = 0;
                return;
            }
            $affected = (int) (clone $query)->update($updates);
        });

        $skipped = max(0, $targeted - $affected);
        $this->forgetSubscribersDynamicExtraColumnsCache($source);
        $this->logFileEvent($request, 'subscriber_bulk_' . $action, $source, [
            'origin' => 'admin.customers.files.subscribers',
            'scope' => $scope,
            'targeted' => $targeted,
            'affected' => $affected,
            'skipped' => $skipped,
            'selected_count' => count($keys),
        ]);

        $payload = [
            'ok' => true,
            'action' => $action,
            'action_label' => $actionLabel,
            'scope' => $scope,
            'targeted' => $targeted,
            'affected' => $affected,
            'skipped' => $skipped,
            'message' => $actionLabel . ': ' . $affected . ' atualizado(s), ' . $skipped . ' sem alteração.',
        ];

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        return redirect()->back()->with('status', (string) $payload['message']);
    }

    public function reprocess(
        Request $request,
        string $id,
        LeadSourceReprocessService $service
    ): RedirectResponse {
        $source = $this->resolveSourceForAction($request, $id);
        $rootSourceId = (int) ($source->parent_source_id ?: $source->id);
        $rootSource = $this->resolveSourceForAction($request, $rootSourceId);

        $service->reprocess((int) $rootSource->id);
        $rootSource->refresh();
        $this->forgetSubscribersDynamicExtraColumnsCache($rootSource);
        $this->logFileEvent($request, 'reprocess', $rootSource, [
            'origin' => 'admin.customers.files',
            'requested_source_id' => (int) $source->id,
            'target_source_id' => (int) $rootSource->id,
        ]);

        return redirect()->back()->with('status', 'Reprocessamento iniciado com sucesso.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $source = $this->resolveSourceForAction($request, $id);
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'admin_tags' => ['nullable', 'string', 'max:500'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $tags = collect(explode(',', (string) ($data['admin_tags'] ?? '')))
            ->map(fn (string $tag): string => trim($tag))
            ->filter(fn (string $tag): bool => $tag !== '')
            ->map(fn (string $tag): string => mb_substr($tag, 0, 40))
            ->unique()
            ->take(20)
            ->values()
            ->all();

        $changes = [];
        $before = [
            'display_name' => $source->display_name,
            'admin_tags_json' => array_values(array_filter(array_map('strval', (array) ($source->admin_tags_json ?? [])), fn (string $v): bool => trim($v) !== '')),
            'admin_notes' => $source->admin_notes,
        ];
        if ($this->hasLeadSourceColumn('display_name')) {
            $changes['display_name'] = trim((string) ($data['display_name'] ?? '')) ?: null;
        }
        if ($this->hasLeadSourceColumn('admin_tags_json')) {
            $changes['admin_tags_json'] = $tags;
        }
        if ($this->hasLeadSourceColumn('admin_notes')) {
            $changes['admin_notes'] = trim((string) ($data['admin_notes'] ?? '')) ?: null;
        }

        $diff = [];
        foreach ($changes as $field => $nextValue) {
            $prevValue = $before[$field] ?? null;
            if (is_array($prevValue) || is_array($nextValue)) {
                $normalizedPrev = array_values(array_filter(array_map('strval', (array) $prevValue), fn (string $v): bool => trim($v) !== ''));
                $normalizedNext = array_values(array_filter(array_map('strval', (array) $nextValue), fn (string $v): bool => trim($v) !== ''));
                if (json_encode($normalizedPrev) !== json_encode($normalizedNext)) {
                    $diff[$field] = ['from' => $normalizedPrev, 'to' => $normalizedNext];
                }
                continue;
            }
            if ((string) ($prevValue ?? '') !== (string) ($nextValue ?? '')) {
                $diff[$field] = ['from' => $prevValue, 'to' => $nextValue];
            }
        }

        if ($changes !== []) {
            $source->fill($changes)->save();
            $this->forgetSubscribersDynamicExtraColumnsCache($source);
            $this->logFileEvent($request, 'edit', $source->fresh(), [
                'origin' => 'admin.customers.files',
                'changed_fields' => array_keys($changes),
                'diff' => $diff,
            ]);
        }

        return redirect()->back()->with('status', 'Arquivo atualizado com sucesso.');
    }

    public function archive(Request $request, string $id): RedirectResponse
    {
        $source = $this->resolveSourceForAction($request, $id);
        $nowArchived = false;

        if ($this->hasLeadSourceColumn('archived_at')) {
            if ($source->archived_at) {
                $source->archived_at = null;
                if ($this->hasLeadSourceColumn('archived_by')) {
                    $source->archived_by = null;
                }
            } else {
                $source->archived_at = now();
                if ($this->hasLeadSourceColumn('archived_by')) {
                    $source->archived_by = (int) auth()->id();
                }
                $nowArchived = true;
            }
            $source->save();
            $this->forgetSubscribersDynamicExtraColumnsCache($source);
        }

        $action = $nowArchived ? 'archive' : 'unarchive';
        $this->logFileEvent($request, $action, $source->fresh(), ['origin' => 'admin.customers.files']);

        return redirect()->back()->with('status', $nowArchived ? 'Arquivo arquivado com sucesso.' : 'Arquivo desarquivado com sucesso.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $source = $this->resolveSourceForAction($request, $id);

        if ($this->hasLeadSourceColumn('deleted_at')) {
            $source->deleted_at = now();
        } else {
            $source->status = 'cancelled';
        }
        if ($this->hasLeadSourceColumn('deleted_by')) {
            $source->deleted_by = (int) auth()->id();
        }
        $source->save();
        $this->forgetSubscribersDynamicExtraColumnsCache($source);

        $this->logFileEvent($request, 'delete', $source->fresh(), ['origin' => 'admin.customers.files', 'soft' => true]);

        return redirect()->back()->with('status', 'Arquivo removido da lista (soft delete) com sucesso.');
    }

    public function history(Request $request, string $id): JsonResponse
    {
        $source = $this->resolveSourceForAction($request, $id);
        $events = [];
        $pagination = [
            'page' => 1,
            'per_page' => 10,
            'total' => 0,
            'last_page' => 1,
        ];
        $actionOptions = [];

        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(['desc', 'asc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        if (Schema::hasTable('guest_file_events')) {
            $actionOptions = DB::table('guest_file_events')
                ->where('lead_source_id', (int) $source->id)
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->map(fn ($item): string => (string) $item)
                ->values()
                ->all();

            $query = $this->historyBaseQuery((int) $source->id, $validated);

            $page = max(1, (int) ($validated['page'] ?? 1));
            $perPage = max(5, min(100, (int) ($validated['per_page'] ?? 10)));
            $sort = (string) ($validated['sort'] ?? 'desc');
            $total = (int) (clone $query)->count('e.id');
            $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
            $page = min($page, $lastPage);

            $rows = $query
                ->orderBy('e.id', $sort === 'asc' ? 'asc' : 'desc')
                ->forPage($page, $perPage)
                ->get([
                    'e.id',
                    'e.action',
                    'e.actor_type',
                    'e.user_id',
                    'e.created_at',
                    'e.payload_json',
                    'u.name as actor_name',
                    'u.email as actor_email',
                ]);

            $events = $this->mapHistoryRows($rows->all());

            $pagination = [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ];
        }

        return response()->json([
            'ok' => true,
            'events' => $events,
            'filters' => [
                'action' => trim((string) ($validated['action'] ?? '')),
                'date_from' => trim((string) ($validated['date_from'] ?? '')),
                'date_to' => trim((string) ($validated['date_to'] ?? '')),
                'sort' => (string) ($validated['sort'] ?? 'desc'),
            ],
            'action_options' => $actionOptions,
            'pagination' => $pagination,
        ]);
    }

    public function historyExport(Request $request, string $id): StreamedResponse
    {
        $source = $this->resolveSourceForAction($request, $id);
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(['desc', 'asc'])],
        ]);

        $sort = (string) ($validated['sort'] ?? 'desc');
        $rows = [];
        if (Schema::hasTable('guest_file_events')) {
            $rows = $this->historyBaseQuery((int) $source->id, $validated)
                ->orderBy('e.id', $sort === 'asc' ? 'asc' : 'desc')
                ->get([
                    'e.id',
                    'e.action',
                    'e.actor_type',
                    'e.user_id',
                    'e.created_at',
                    'e.payload_json',
                    'u.name as actor_name',
                    'u.email as actor_email',
                ])
                ->all();
        }

        $events = $this->mapHistoryRows($rows);
        $filename = 'updates-history-source-' . (int) $source->id . '.csv';

        $this->logFileEvent($request, 'history_export', $source, [
            'origin' => 'admin.customers.files',
            'filters' => [
                'action' => trim((string) ($validated['action'] ?? '')),
                'date_from' => trim((string) ($validated['date_from'] ?? '')),
                'date_to' => trim((string) ($validated['date_to'] ?? '')),
                'sort' => $sort,
            ],
        ]);

        return response()->streamDownload(function () use ($events): void {
            $out = fopen('php://output', 'wb');
            if (!$out) {
                return;
            }

            fputcsv($out, ['id', 'action', 'actor_type', 'actor_name', 'actor_email', 'created_at', 'details']);
            foreach ($events as $event) {
                $details = '-';
                $diff = $event['payload']['diff'] ?? null;
                if (is_array($diff) && $diff !== []) {
                    $parts = [];
                    foreach ($diff as $field => $values) {
                        $from = is_array($values['from'] ?? null) ? implode(', ', $values['from']) : (string) ($values['from'] ?? '—');
                        $to = is_array($values['to'] ?? null) ? implode(', ', $values['to']) : (string) ($values['to'] ?? '—');
                        $parts[] = $field . ': ' . $from . ' -> ' . $to;
                    }
                    $details = implode(' | ', $parts);
                } elseif (!empty($event['payload']['changed_fields']) && is_array($event['payload']['changed_fields'])) {
                    $details = 'changed_fields: ' . implode(', ', $event['payload']['changed_fields']);
                } elseif (!empty($event['payload']['mvp_mode'])) {
                    $details = 'mode: ' . (string) $event['payload']['mvp_mode'];
                }

                fputcsv($out, [
                    (int) $event['id'],
                    (string) $event['action'],
                    (string) $event['actor_type'],
                    (string) ($event['actor_name'] ?? ''),
                    (string) ($event['actor_email'] ?? ''),
                    (string) $event['created_at'],
                    $details,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function retryFailedRows(
        Request $request,
        string $id,
        LeadSourceReprocessService $service
    ): RedirectResponse {
        $source = $this->resolveSourceForAction($request, $id);
        if (!in_array((string) $source->status, ['failed', 'canceled', 'cancelled'], true)) {
            return redirect()->back()->with('status', 'Retry disponível apenas para arquivos com falha/cancelados.');
        }

        $service->reprocess((int) $source->id);
        $source->refresh();
        $this->forgetSubscribersDynamicExtraColumnsCache($source);
        $this->logFileEvent($request, 'retry_failed_rows', $source, [
            'origin' => 'admin.customers.files',
            'mvp_mode' => 'full_reprocess',
        ]);

        return redirect()->back()->with('status', 'Retry de falhas iniciado (MVP v1: reprocessamento completo).');
    }

    public function errorReport(Request $request, string $id): StreamedResponse
    {
        $source = $this->resolveSourceForAction($request, $id);
        $filename = 'error-report-source-' . (int) $source->id . '.csv';

        $this->logFileEvent($request, 'error_report_download', $source, ['origin' => 'admin.customers.files']);

        return response()->streamDownload(function () use ($source): void {
            $out = fopen('php://output', 'wb');
            if (!$out) {
                return;
            }

            fputcsv($out, ['source_id', 'file_name', 'status', 'processed_rows', 'total_rows', 'last_error']);
            fputcsv($out, [
                (int) $source->id,
                (string) ($source->display_name ?: $source->original_name),
                (string) $source->status,
                (int) ($source->processed_rows ?? 0),
                (int) ($source->total_rows ?? 0),
                preg_replace('/\\s+/', ' ', trim((string) ($source->last_error ?? ''))) ?: '',
            ]);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function baseScopeQuery(bool $isGlobalSuper, string $tenantUuid): Builder
    {
        $query = $isGlobalSuper
            ? LeadSource::withoutGlobalScopes()
            : LeadSource::query()->where('tenant_uuid', $tenantUuid);

        if ($isGlobalSuper && $tenantUuid !== '') {
            $query->where('tenant_uuid', $tenantUuid);
        }
        if ($this->hasLeadSourceColumn('deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }
        $hasDisplayName = $this->hasLeadSourceColumn('display_name');

        return $query->where(function (Builder $inner) use ($search, $hasDisplayName): void {
            $inner->where('original_name', 'like', '%' . $search . '%');
            if ($hasDisplayName) {
                $inner->orWhere('display_name', 'like', '%' . $search . '%');
            }
            if ($this->hasLeadSourceColumn('public_uid')) {
                $inner->orWhere('public_uid', 'like', '%' . $search . '%');
            }
            $inner
                ->orWhere('file_path', 'like', '%' . $search . '%')
                ->orWhere('file_hash', 'like', '%' . $search . '%')
                ->orWhere('id', 'like', '%' . $search . '%');
        });
    }

    private function applyArchivedMode(Builder $query, string $archivedMode): Builder
    {
        if (!$this->hasLeadSourceColumn('archived_at')) {
            return $query;
        }

        if ($archivedMode === 'only') {
            return $query->whereNotNull('archived_at');
        }

        return $query->whereNull('archived_at');
    }

    private function isGlobalSuperAdminContext(): bool
    {
        return auth()->check()
            && auth()->user()?->isSuperAdmin()
            && !session()->has('impersonate_user_id');
    }

    private function resolveTenantUuid(Request $request, bool $isGlobalSuper): string
    {
        if ($isGlobalSuper) {
            return trim((string) $request->query('tenant_uuid', ''));
        }

        return app()->bound('tenant_uuid') ? trim((string) app('tenant_uuid')) : '';
    }

    private function resolveSourceForAction(Request $request, string|int $id): LeadSource
    {
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $tenantUuid = trim((string) $request->input('tenant_uuid', ''));
        if (!$isGlobalSuper) {
            $tenantUuid = app()->bound('tenant_uuid') ? trim((string) app('tenant_uuid')) : '';
        }

        $routeKey = trim((string) $id);

        return $this->baseScopeQuery($isGlobalSuper, $tenantUuid)
            ->where(function (Builder $inner) use ($routeKey): void {
                if (ctype_digit($routeKey)) {
                    $inner->whereKey((int) $routeKey);
                }
                if ($this->hasLeadSourceColumn('public_uid')) {
                    $inner->orWhere('public_uid', $routeKey);
                }
            })
            ->firstOrFail();
    }

    private function hasLeadSourceColumn(string $column): bool
    {
        static $cache = [];
        if (!array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasColumn('lead_sources', $column);
        }

        return (bool) $cache[$column];
    }

    private function hasLeadNormalizedColumn(string $column): bool
    {
        static $cache = [];
        if (!array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasColumn('leads_normalized', $column);
        }

        return (bool) $cache[$column];
    }

    private function logFileEvent(Request $request, string $action, LeadSource $source, array $payload = []): void
    {
        try {
            app(GuestAuditService::class)->logFileEvent($request, $action, [
                'lead_source_id' => (int) $source->id,
                'file_name' => (string) ($source->display_name ?: $source->original_name),
                'file_path' => (string) ($source->file_path ?? ''),
                'file_hash' => (string) ($source->file_hash ?? ''),
                'file_size_bytes' => (int) ($source->file_size_bytes ?? 0),
                'payload' => $payload,
            ]);
        } catch (\Throwable) {
            // Audit logging must not break admin actions.
        }
    }

    private function historyBaseQuery(int $sourceId, array $filters): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('guest_file_events as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.lead_source_id', $sourceId);

        $actionFilter = trim((string) ($filters['action'] ?? ''));
        if ($actionFilter !== '') {
            $query->where('e.action', $actionFilter);
        }
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('e.created_at', '>=', $dateFrom);
        }
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('e.created_at', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * @param array<int,object> $rows
     * @return array<int,array<string,mixed>>
     */
    private function mapHistoryRows(array $rows): array
    {
        return collect($rows)->map(function ($row): array {
            $payload = [];
            if (!empty($row->payload_json)) {
                $decoded = json_decode((string) $row->payload_json, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            return [
                'id' => (int) $row->id,
                'action' => (string) $row->action,
                'actor_type' => (string) ($row->actor_type ?? 'user'),
                'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                'actor_name' => $row->actor_name !== null ? (string) $row->actor_name : null,
                'actor_email' => $row->actor_email !== null ? (string) $row->actor_email : null,
                'created_at' => (string) $row->created_at,
                'payload' => $payload,
            ];
        })->values()->all();
    }

    /**
     * @return array{labels: array<int,string>, values: array<int,int>}
     */
    private function buildUpdatesSeries(int $sourceId): array
    {
        $labels = [];
        $values = [];
        $days = collect(range(6, 0))->map(fn (int $offset) => now()->startOfDay()->subDays($offset));

        foreach ($days as $day) {
            $labels[] = $day->format('d/m');
            $values[] = 0;
        }

        if (!Schema::hasTable('guest_file_events')) {
            return ['labels' => $labels, 'values' => $values];
        }

        $rows = DB::table('guest_file_events')
            ->where('lead_source_id', $sourceId)
            ->whereDate('created_at', '>=', now()->startOfDay()->subDays(6)->toDateString())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day');

        foreach ($days as $index => $day) {
            $values[$index] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: array<int,string>, values: array<int,int>}
     */
    private function buildListGrowthSeries(int $sourceId): array
    {
        $labels = [];
        $values = [];
        $days = collect(range(6, 0))->map(fn (int $offset) => now()->startOfDay()->subDays($offset));

        foreach ($days as $day) {
            $labels[] = $day->format('d/m');
            $values[] = 0;
        }

        if (!Schema::hasTable('leads_normalized')) {
            return ['labels' => $labels, 'values' => $values];
        }

        $rows = DB::table('leads_normalized')
            ->where('lead_source_id', $sourceId)
            ->whereDate('created_at', '>=', now()->startOfDay()->subDays(6)->toDateString())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day');

        $cumulative = 0;
        foreach ($days as $index => $day) {
            $cumulative += (int) ($rows[$day->toDateString()] ?? 0);
            $values[$index] = $cumulative;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function subscribersQuery(Request $request, LeadSource $source, bool $isGlobalSuper): Builder
    {
        $tenantUuid = (string) ($source->tenant_uuid ?? '');
        $query = $isGlobalSuper
            ? LeadNormalized::withoutGlobalScopes()->where('tenant_uuid', $tenantUuid)
            : LeadNormalized::query();

        $query->where('lead_source_id', (int) $source->id);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', '%' . $search . '%')
                    ->orWhere('cpf', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone_e164', 'like', '%' . $search . '%')
                    ->orWhere('whatsapp_e164', 'like', '%' . $search . '%')
                    ->orWhere('city', 'like', '%' . $search . '%')
                    ->orWhere('uf', 'like', '%' . $search . '%')
                    ->orWhere('sex', 'like', '%' . $search . '%')
                    ->orWhere('entity_type', 'like', '%' . $search . '%')
                    ->orWhere('lifecycle_stage', 'like', '%' . $search . '%');
            });
        }

        $scoreMin = (int) $request->query('score_min', 0);
        if ($scoreMin > 0) {
            $query->where('score', '>=', max(0, min(100, $scoreMin)));
        }

        return $query;
    }

    private function applySubscriberRouteKeysFilter(Builder $query, array $routeKeys): Builder
    {
        $ids = [];
        $uids = [];
        foreach ($routeKeys as $routeKey) {
            $key = trim((string) $routeKey);
            if ($key === '') {
                continue;
            }
            if (ctype_digit($key)) {
                $ids[] = (int) $key;
                continue;
            }
            $uids[] = $key;
        }
        $ids = array_values(array_unique($ids));
        $uids = array_values(array_unique($uids));

        if ($ids === [] && $uids === []) {
            return $query->whereRaw('1=0');
        }

        return $query->where(function (Builder $inner) use ($ids, $uids): void {
            if ($ids !== []) {
                $inner->whereIn('id', $ids);
            }
            if ($uids !== [] && $this->hasLeadNormalizedColumn('public_uid')) {
                if ($ids !== []) {
                    $inner->orWhereIn('public_uid', $uids);
                } else {
                    $inner->whereIn('public_uid', $uids);
                }
            }
        });
    }

    private function resolveSubscriberForSource(LeadSource $source, string|int $subscriberId): LeadNormalized
    {
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $tenantUuid = (string) ($source->tenant_uuid ?? '');
        $routeKey = trim((string) $subscriberId);

        $query = $isGlobalSuper
            ? LeadNormalized::withoutGlobalScopes()->where('tenant_uuid', $tenantUuid)
            : LeadNormalized::query();

        return $query
            ->where('lead_source_id', (int) $source->id)
            ->where(function (Builder $inner) use ($routeKey): void {
                if (ctype_digit($routeKey)) {
                    $inner->whereKey((int) $routeKey);
                }
                if (Schema::hasColumn('leads_normalized', 'public_uid')) {
                    $inner->orWhere('public_uid', $routeKey);
                }
            })
            ->firstOrFail();
    }

    /**
     * @return array<int, array{key:string,label:string,col:string}>
     */
    private function resolveSubscribersDynamicExtraColumns(LeadSource $source, bool $isGlobalSuper): array
    {
        $cacheKey = $this->subscribersDynamicExtraColumnsCacheKey($source);
        $ttl = now()->addMinutes(self::DYNAMIC_EXTRA_COLUMNS_CACHE_MINUTES);

        try {
            return Cache::remember($cacheKey, $ttl, function () use ($source, $isGlobalSuper): array {
                return $this->buildSubscribersDynamicExtraColumns($source, $isGlobalSuper);
            });
        } catch (\Throwable) {
            return $this->buildSubscribersDynamicExtraColumns($source, $isGlobalSuper);
        }
    }

    private function subscribersDynamicExtraColumnsCacheKey(LeadSource $source): string
    {
        $tenantUuid = trim((string) ($source->tenant_uuid ?? ''));
        $tenantPart = $tenantUuid !== '' ? $tenantUuid : 'global';

        return 'admin:lists:dynamic-extra-columns:v1:' . $tenantPart . ':' . (int) $source->id;
    }

    private function forgetSubscribersDynamicExtraColumnsCache(LeadSource $source): void
    {
        try {
            Cache::forget($this->subscribersDynamicExtraColumnsCacheKey($source));
        } catch (\Throwable) {
            // Cache invalidation must not block admin actions.
        }
    }

    /**
     * @return array<int, array{key:string,label:string,col:string}>
     */
    private function buildSubscribersDynamicExtraColumns(LeadSource $source, bool $isGlobalSuper): array
    {
        $tenantUuid = (string) ($source->tenant_uuid ?? '');
        $query = $isGlobalSuper
            ? LeadNormalized::withoutGlobalScopes()->where('tenant_uuid', $tenantUuid)
            : LeadNormalized::query();

        $rows = $query
            ->where('lead_source_id', (int) $source->id)
            ->whereNotNull('extras_json')
            ->orderByDesc('id')
            ->limit(300)
            ->pluck('extras_json');

        $keys = [];
        foreach ($rows as $extras) {
            if (!is_array($extras)) {
                continue;
            }
            foreach ($extras as $key => $value) {
                $key = trim((string) $key);
                if ($key === '') {
                    continue;
                }
                $keys[$key] = true;
            }
        }

        $columns = [];
        foreach (array_keys($keys) as $key) {
            if ($this->standardColumnFromKey($key) !== null) {
                continue;
            }
            $col = 'extra_' . substr(md5($key), 0, 10);
            $label = str_replace('_', ' ', $key);
            $label = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
            $columns[] = [
                'key' => $key,
                'label' => $label,
                'col' => $col,
            ];
        }

        usort($columns, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $columns;
    }

    private function extractExploreStandardColumns(LeadNormalized $subscriber): array
    {
        $extras = is_array($subscriber->extras_json ?? null) ? $subscriber->extras_json : [];
        $standard = [
            'nome' => trim((string) ($subscriber->name ?? '')),
            'cpf' => trim((string) ($subscriber->cpf ?? '')),
            'email' => trim((string) ($subscriber->email ?? '')),
            'phone' => trim((string) (($subscriber->phone_e164 ?? '') ?: ($subscriber->phone ?? ''))),
            'data_nascimento' => '',
            'sex' => trim((string) ($subscriber->sex ?? '')),
            'score' => $subscriber->score !== null ? (int) $subscriber->score : 0,
        ];

        foreach ($extras as $key => $value) {
            $canonical = $this->standardColumnFromKey((string) $key);
            if ($canonical === null) {
                continue;
            }
            $valueString = is_scalar($value) ? trim((string) $value) : '';
            if ($valueString === '') {
                continue;
            }
            if ($canonical === 'score') {
                if ((int) $standard['score'] <= 0 && is_numeric($valueString)) {
                    $standard['score'] = max(0, min(100, (int) $valueString));
                }
                continue;
            }
            if (trim((string) ($standard[$canonical] ?? '')) === '') {
                $standard[$canonical] = $valueString;
            }
        }

        $filteredExtras = [];
        foreach ($extras as $key => $value) {
            if ($this->standardColumnFromKey((string) $key) !== null) {
                continue;
            }
            $filteredExtras[(string) $key] = $value;
        }

        return [$standard, $filteredExtras];
    }

    private function formatExploreStandardColumns(array $standard): array
    {
        $score = isset($standard['score']) ? (int) $standard['score'] : 0;

        return [
            'nome' => trim((string) ($standard['nome'] ?? '')),
            'cpf' => $this->formatExploreCpf((string) ($standard['cpf'] ?? '')),
            'email' => trim((string) ($standard['email'] ?? '')),
            'phone' => $this->formatExplorePhone((string) ($standard['phone'] ?? '')),
            'data_nascimento' => trim((string) ($standard['data_nascimento'] ?? '')),
            'sex' => $this->formatExploreSex((string) ($standard['sex'] ?? '')),
            'score' => max(0, min(100, $score)),
        ];
    }

    private function formatExploreCpf(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value)) ?? '';
        if ($digits === '') {
            return '';
        }

        $digits = substr($digits, 0, 11);

        $masked = preg_replace('/(\d{3})(\d)/', '$1.$2', $digits, 1);
        $masked = preg_replace('/(\d{3})(\d)/', '$1.$2', (string) $masked, 1);
        $masked = preg_replace('/(\d{3})(\d{1,2})$/', '$1-$2', (string) $masked);

        return (string) ($masked ?? $digits);
    }

    private function formatExplorePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value)) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0055')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '055')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '55') && strlen($digits) > 11) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) < 10) {
            return $value;
        }

        $ddd = substr($digits, 0, 2);
        $number = substr($digits, 2);

        if (strlen($number) === 9) {
            return '+55 (' . $ddd . ') ' . substr($number, 0, 5) . '-' . substr($number, 5);
        }

        if (strlen($number) === 8) {
            return '+55 (' . $ddd . ') ' . substr($number, 0, 4) . '-' . substr($number, 4);
        }

        return '+55 (' . $ddd . ') ' . $number;
    }

    private function formatExploreSex(string $value): string
    {
        $normalized = strtoupper(trim($value));
        if ($normalized === 'M') {
            return '♂';
        }
        if ($normalized === 'F') {
            return '♀';
        }

        return '';
    }

    /**
     * @return array{screen:string,start_at:float,query_log_enabled:bool}
     */
    private function startPerformanceProbe(string $screen): array
    {
        $enabled = (bool) config('performance.admin_lists.enabled', true);
        if ($enabled) {
            DB::flushQueryLog();
            DB::enableQueryLog();
        }

        return [
            'screen' => $screen,
            'start_at' => microtime(true),
            'query_log_enabled' => $enabled,
        ];
    }

    /**
     * @param array{screen:string,start_at:float,query_log_enabled:bool} $probe
     * @param array<string,mixed> $context
     */
    private function finishPerformanceProbe(Request $request, string $screen, array $probe, array $context = []): void
    {
        if (!($probe['query_log_enabled'] ?? false)) {
            return;
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $queryCount = count($queries);
        $queryMs = (float) array_reduce($queries, static function (float $carry, array $query): float {
            return $carry + (float) ($query['time'] ?? 0.0);
        }, 0.0);
        $totalMs = (microtime(true) - (float) ($probe['start_at'] ?? microtime(true))) * 1000;

        /** @var array{total_ms?:int,query_ms?:int,query_count?:int,payload_items?:int} $budget */
        $budget = (array) config('performance.admin_lists.budgets.' . $screen, []);
        $payloadItems = (int) ($context['payload_items'] ?? 0);

        $isSlow = (
            (isset($budget['total_ms']) && $totalMs > (int) $budget['total_ms']) ||
            (isset($budget['query_ms']) && $queryMs > (int) $budget['query_ms']) ||
            (isset($budget['query_count']) && $queryCount > (int) $budget['query_count']) ||
            (isset($budget['payload_items']) && $payloadItems > (int) $budget['payload_items'])
        );

        $logContext = array_merge($context, [
            'route' => (string) ($request->route()?->getName() ?? ''),
            'path' => $request->path(),
            'method' => $request->method(),
            'screen' => $screen,
            'total_ms' => round($totalMs, 2),
            'query_ms' => round($queryMs, 2),
            'query_count' => $queryCount,
            'budget' => $budget,
        ]);

        if ($isSlow) {
            Log::warning('admin lists performance budget exceeded', $logContext);
            return;
        }

        Log::info('admin lists performance', $logContext);
    }

    private function standardColumnFromKey(string $key): ?string
    {
        return StandardColumnsSchema::canonicalFromKey($key);
    }
}
