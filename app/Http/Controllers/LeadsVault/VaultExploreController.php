<?php

namespace App\Http\Controllers\LeadsVault;

use App\Jobs\ImportLeadSourceJob;
use App\Jobs\SecuritySyncMissingJob;
use App\Http\Controllers\Controller;
use App\Models\ExploreViewPreference;
use App\Models\LeadOverride;
use App\Models\LeadNormalized;
use App\Models\LeadSource;
use App\Services\GuestAuditService;
use App\Services\GuestIdentityService;
use App\Services\LeadDataQualityService;
use App\Support\Brazil\Cpf;
use App\Support\TenantStorage;
use App\Services\VaultSemanticService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VaultExploreController extends Controller
{
    private function isGlobalSuperAdminContext(): bool
    {
        return auth()->check()
            && auth()->user()?->isSuperAdmin()
            && !session()->has('impersonate_user_id');
    }

    private function ensureGuestTenant(): string
    {
        $guestUuid = app(GuestIdentityService::class)->ensureGuestUuid(request());
        app()->instance('tenant_uuid', $guestUuid);
        view()->share('tenant_uuid', $guestUuid);
        app(GuestAuditService::class)->touchGuestSession(request(), [
            'scope' => 'explore_guest',
        ]);

        return $guestUuid;
    }

    private function isGuest(): bool
    {
        return !auth()->check();
    }

    private function resolveTenantUuidForSource(?int $sourceId = null): ?string
    {
        $tenantUuid = TenantStorage::tenantUuidOrNull();
        if (!$this->isGlobalSuperAdminContext()) {
            return $tenantUuid;
        }

        if ($sourceId && $sourceId > 0) {
            $source = LeadSource::withoutGlobalScopes()
                ->whereKey($sourceId)
                ->first(['id', 'tenant_uuid']);

            if ($source && !empty($source->tenant_uuid)) {
                $tenantUuid = (string) $source->tenant_uuid;
                session(['tenant_uuid_override' => $tenantUuid]);
                app()->instance('tenant_uuid', $tenantUuid);
                view()->share('tenant_uuid', $tenantUuid);
                return $tenantUuid;
            }
        }

        $override = trim((string) session('tenant_uuid_override', ''));
        if ($override !== '') {
            app()->instance('tenant_uuid', $override);
            view()->share('tenant_uuid', $override);
            return $override;
        }

        return $tenantUuid;
    }

    private function resolveTenantUuidForSemanticSource(?int $sourceId): ?string
    {
        if ($sourceId && $sourceId > 0 && $this->isGlobalSuperAdminContext()) {
            $source = LeadSource::withoutGlobalScopes()
                ->whereKey($sourceId)
                ->first(['id', 'tenant_uuid']);
            if ($source && filled($source->tenant_uuid)) {
                $tenantUuid = (string) $source->tenant_uuid;
                session(['tenant_uuid_override' => $tenantUuid]);
                app()->instance('tenant_uuid', $tenantUuid);
                view()->share('tenant_uuid', $tenantUuid);
                return $tenantUuid;
            }
        }

        return TenantStorage::requireTenantUuid();
    }

    /* ======================================================
     | VIEW
     ====================================================== */

    public function index()
    {
        $isGuest = $this->isGuest();
        if ($isGuest) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('viewAny', LeadNormalized::class);
        }
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $sourceId = session('explore_source_id');
        $tenantUuid = $this->resolveTenantUuidForSource($sourceId ? (int) $sourceId : null);
        $hasExploreViewPrefs = Schema::hasTable('explore_view_preferences');
        $sourcesQuery = $isGlobalSuper
            ? LeadSource::withoutGlobalScopes()
            : LeadSource::query()->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid));
        $hasSources = $sourcesQuery->exists();
        $forceImportGate = false;

        if (!$hasSources) {
            session()->forget('explore_source_id');
            $sourceId = null;
        }

        if ($sourceId) {
            $hasSelectedSource = $isGlobalSuper
                ? LeadSource::withoutGlobalScopes()->whereKey($sourceId)->exists()
                : LeadSource::query()
                    ->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
                    ->whereKey($sourceId)
                    ->exists();

            if (!$hasSelectedSource) {
                session()->forget('explore_source_id');
                $sourceId = null;
            }
        }

        if ($tenantUuid) {
            $this->normalizeLegacyLeadColumnKey((string) $tenantUuid, null);
            if ($sourceId) {
                $this->normalizeLegacyLeadColumnKey((string) $tenantUuid, (int) $sourceId);
            }
        }

        $columnSettings = \App\Models\LeadColumnSetting::query()
            ->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->when(
                $sourceId,
                fn ($q) => $q->where('lead_source_id', $sourceId),
                fn ($q) => $q->whereNull('lead_source_id')
            )
            ->orderBy('sort_order')
            ->get([
                'column_key',
                'label',
                'visible',
                'sort_order',
            ]);

        $userId = auth()->id();
        $sourceScope = $sourceId ? "source:{$sourceId}" : 'global';

        $viewPreference = null;
        if ($hasExploreViewPrefs) {
            $viewPreference = ExploreViewPreference::query()
                ->where('user_id', $userId)
                ->where('scope_key', $sourceScope)
                ->first([
                    'lead_source_id',
                    'scope_key',
                    'visible_columns',
                    'column_order',
                ]);

            if (!$viewPreference && $sourceId) {
                $viewPreference = ExploreViewPreference::query()
                    ->where('user_id', $userId)
                    ->where('scope_key', 'global')
                    ->first([
                        'lead_source_id',
                        'scope_key',
                        'visible_columns',
                        'column_order',
                    ]);
            }
        }

        return view('vault.explore.index', [
            'columnSettings' => $columnSettings,
            'viewPreference' => $viewPreference,
            'hasSources' => $hasSources,
            'forceImportGate' => $forceImportGate,
            'isGuest' => $isGuest,
        ]);
    }

    private function normalizeLegacyLeadColumnKey(string $tenantUuid, ?int $sourceId): void
    {
        $hasNome = \App\Models\LeadColumnSetting::query()
            ->where('tenant_uuid', $tenantUuid)
            ->when(
                $sourceId === null,
                fn ($q) => $q->whereNull('lead_source_id'),
                fn ($q) => $q->where('lead_source_id', $sourceId)
            )
            ->where('column_key', 'nome')
            ->exists();

        if ($hasNome) {
            return;
        }

        \App\Models\LeadColumnSetting::query()
            ->where('tenant_uuid', $tenantUuid)
            ->when(
                $sourceId === null,
                fn ($q) => $q->whereNull('lead_source_id'),
                fn ($q) => $q->where('lead_source_id', $sourceId)
            )
            ->where('column_key', 'lead')
            ->limit(1)
            ->update([
                'column_key' => 'nome',
                'label' => 'Nome',
            ]);
    }

    public function selectSource(int $id, Request $request, GuestAuditService $guestAudit)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        }

        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $source = $isGlobalSuper
            ? LeadSource::withoutGlobalScopes()->findOrFail($id)
            : LeadSource::findOrFail($id);

        if (!$this->isGuest()) {
            $this->authorize('view', $source);
        }

        if ($isGlobalSuper && !empty($source->tenant_uuid)) {
            session(['tenant_uuid_override' => (string) $source->tenant_uuid]);
            app()->instance('tenant_uuid', (string) $source->tenant_uuid);
            view()->share('tenant_uuid', (string) $source->tenant_uuid);
        }

        $tenantUuid = $isGlobalSuper
            ? (string) ($source->tenant_uuid ?? '')
            : TenantStorage::tenantUuidOrNull();

        $resolvedId = $id;
        if ($tenantUuid) {
            $resolvedId = $this->resolveActiveSourceIdForSelection($source, (string) $tenantUuid);
        }
        session(['explore_source_id' => $resolvedId]);

        $guestAudit->logFileEvent($request, 'source_select', [
            'lead_source_id' => (int) $source->id,
            'file_name' => (string) $source->original_name,
            'file_path' => (string) $source->file_path,
            'file_hash' => (string) $source->file_hash,
            'file_size_bytes' => (int) $source->file_size_bytes,
            'payload' => [
                'mode' => 'source_select',
                'requested_source_id' => (int) $id,
                'resolved_source_id' => (int) $resolvedId,
            ],
        ]);

        return redirect()->route('home');
    }

    public function clearSource(Request $request, GuestAuditService $guestAudit)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        }
        if (!$this->isGuest()) {
            $this->authorize('viewAny', LeadNormalized::class);
        }

        $selectedId = (int) session('explore_source_id', 0);
        $selectedSource = null;
        if ($selectedId > 0) {
            $selectedSource = LeadSource::query()->whereKey($selectedId)->first([
                'id',
                'original_name',
                'file_path',
                'file_hash',
                'file_size_bytes',
            ]);
        }

        session()->forget('explore_source_id');

        if ($selectedId > 0) {
            $guestAudit->logFileEvent($request, 'source_clear', [
                'lead_source_id' => (int) ($selectedSource->id ?? $selectedId),
                'file_name' => (string) ($selectedSource->original_name ?? ''),
                'file_path' => (string) ($selectedSource->file_path ?? ''),
                'file_hash' => (string) ($selectedSource->file_hash ?? ''),
                'file_size_bytes' => (int) ($selectedSource->file_size_bytes ?? 0),
                'payload' => [
                    'mode' => 'source_clear',
                    'selected_source_id' => $selectedId,
                ],
            ]);
        }

        return redirect()->route('home');
    }

    public function sourcesList()
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('viewAny', LeadNormalized::class);
        }
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $tenantUuid = TenantStorage::tenantUuidOrNull();

        $allSources = $isGlobalSuper
            ? LeadSource::withoutGlobalScopes()
                ->orderByDesc('id')
                ->get(['id', 'tenant_uuid', 'original_name', 'created_at', 'updated_at', 'parent_source_id'])
            : LeadSource::query()
                ->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
                ->orderByDesc('id')
                ->get(['id', 'original_name', 'created_at', 'updated_at', 'parent_source_id']);

        $byId = $allSources->keyBy('id');
        $roots = [];
        $latestByRoot = [];

        foreach ($allSources as $item) {
            $rootId = !empty($item->parent_source_id)
                ? (int) $item->parent_source_id
                : (int) $item->id;

            if (!isset($roots[$rootId])) {
                $root = $byId->get($rootId);
                $roots[$rootId] = $root ?: $item;
            }

            if (
                !isset($latestByRoot[$rootId])
                || $item->updated_at?->gt($latestByRoot[$rootId]->updated_at)
                || (
                    $item->updated_at?->equalTo($latestByRoot[$rootId]->updated_at)
                    && (int) $item->id > (int) $latestByRoot[$rootId]->id
                )
            ) {
                $latestByRoot[$rootId] = $item;
            }
        }

        $sources = collect($latestByRoot)
            ->map(function ($active, $rootId) use ($roots, $isGlobalSuper) {
                $root = $roots[$rootId] ?? $active;
                $label = $this->canonicalSourceName((string) ($root->original_name ?? $active->original_name));
                if ($isGlobalSuper && !empty($active->tenant_uuid)) {
                    $label .= ' · ' . (string) $active->tenant_uuid;
                }
                return [
                    'id' => (int) $active->id,
                    'original_name' => $label,
                    'created_at' => $active->created_at,
                ];
            })
            ->sortByDesc('id')
            ->values();

        $current = session('explore_source_id');
        if ($current) {
            $currentSource = $byId->get((int) $current);
            if ($currentSource) {
                $rootId = !empty($currentSource->parent_source_id)
                    ? (int) $currentSource->parent_source_id
                    : (int) $currentSource->id;
                $resolvedCurrent = isset($latestByRoot[$rootId]) ? (int) $latestByRoot[$rootId]->id : (int) $current;
                if ($resolvedCurrent !== (int) $current) {
                    session(['explore_source_id' => $resolvedCurrent]);
                    $current = $resolvedCurrent;
                }
            } else {
                session()->forget('explore_source_id');
                $current = null;
            }
        }

        return response()->json([
            'current' => $current,
            'sources' => $sources,
        ]);
    }

    public function saveViewPreference(Request $request)
    {
        $this->authorize('viewAny', LeadNormalized::class);

        if (!Schema::hasTable('explore_view_preferences')) {
            return response()->json([
                'ok' => false,
                'message' => 'table_not_ready',
            ], 503);
        }

        $data = $request->validate([
            'lead_source_id' => ['nullable', 'integer', 'min:1'],
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string', 'max:120'],
            'column_order' => ['nullable', 'array'],
            'column_order.*' => ['string', 'max:120'],
        ]);

        $userId = (int) auth()->id();
        $sourceId = isset($data['lead_source_id']) ? (int) $data['lead_source_id'] : null;
        $tenantUuid = TenantStorage::requireTenantUuid();

        if ($sourceId) {
            $source = $this->isGlobalSuperAdminContext()
                ? LeadSource::withoutGlobalScopes()->findOrFail($sourceId)
                : LeadSource::findOrFail($sourceId);
            $this->authorize('view', $source);
            $tenantUuid = (string) $source->tenant_uuid;
            if ($tenantUuid !== '') {
                session(['tenant_uuid_override' => $tenantUuid]);
                app()->instance('tenant_uuid', $tenantUuid);
                view()->share('tenant_uuid', $tenantUuid);
            }
        }

        $scopeKey = $sourceId ? "source:{$sourceId}" : 'global';
        $visibleColumns = array_values(array_unique(array_map('strval', $data['visible_columns'] ?? [])));
        $columnOrder = array_values(array_unique(array_map('strval', $data['column_order'] ?? [])));

        ExploreViewPreference::query()->updateOrCreate(
            [
                'tenant_uuid' => $tenantUuid,
                'user_id' => $userId,
                'scope_key' => $scopeKey,
            ],
            [
                'lead_source_id' => $sourceId,
                'visible_columns' => $visibleColumns,
                'column_order' => $columnOrder,
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'saved',
        ]);
    }

    public function semanticOptions()
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('viewAny', LeadNormalized::class);
        }
        $citiesHaveState = Schema::hasColumn('semantic_cities', 'state_id');
        $cityColumns = $citiesHaveState ? ['id', 'name', 'state_id'] : ['id', 'name'];

        $cities = DB::table('semantic_cities')
            ->orderBy('name')
            ->get($cityColumns);

        if (!$citiesHaveState) {
            $cities = $cities->map(function ($row) {
                $row->state_id = null;
                return $row;
            });
        }

        return response()->json([
            'cities'    => $cities,
            'cities_have_state' => $citiesHaveState,
            'states'    => DB::table('semantic_states')->orderBy('name')->get(['id','name']),
            'countries' => DB::table('semantic_countries')->orderBy('name')->get(['id','name']),
            'segments'  => DB::table('semantic_segments')->orderBy('name')->get(['id','name']),
            'niches'    => DB::table('semantic_niches')->orderBy('name')->get(['id','name']),
            'origins'   => DB::table('semantic_origins')->orderBy('name')->get(['id','name']),
        ]);
    }

    public function semanticAutocomplete(Request $request)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('viewAny', LeadNormalized::class);
        }
        $q = trim((string) $request->query('q', ''));
        $type = $request->query('type');
        abort_if(strlen($q) < 2, 422);

        $table = match ($type) {
            'city' => 'semantic_cities',
            'state' => 'semantic_states',
            'country' => 'semantic_countries',
            'segment' => 'semantic_segments',
            'niche' => 'semantic_niches',
            'origin' => 'semantic_origins',
            default => null,
        };

        if (!$table) {
            return response()->json(['items' => []]);
        }

        $query = DB::table($table)->where('name', 'like', "%{$q}%");

        if ($type === 'city' && Schema::hasColumn('semantic_cities', 'state_id')) {
            $stateIds = array_filter(array_map('intval', (array) $request->query('state_ids', [])));
            if ($stateIds) {
                $query->whereIn('state_id', $stateIds);
            }
        }

        $items = $query->orderBy('name')->limit(25)->get(['id','name']);

        return response()->json(['items' => $items]);
    }

    public function semanticAutocompleteUnified(Request $request)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('viewAny', LeadNormalized::class);
        }
        $q = trim((string) $request->query('q', ''));
        abort_if(strlen($q) < 2, 422);

        $like = "%{$q}%";

        $items = [];

        $segments = DB::table('semantic_segments')->where('name', 'like', $like)->limit(10)->get(['id','name']);
        foreach ($segments as $row) {
            $items[] = ['taxonomy' => 'segment', 'id' => $row->id, 'label' => $row->name, 'type' => null, 'ref_id' => null];
        }

        $niches = DB::table('semantic_niches')->where('name', 'like', $like)->limit(10)->get(['id','name']);
        foreach ($niches as $row) {
            $items[] = ['taxonomy' => 'niche', 'id' => $row->id, 'label' => $row->name, 'type' => null, 'ref_id' => null];
        }

        $origins = DB::table('semantic_origins')->where('name', 'like', $like)->limit(10)->get(['id','name']);
        foreach ($origins as $row) {
            $items[] = ['taxonomy' => 'origin', 'id' => $row->id, 'label' => $row->name, 'type' => null, 'ref_id' => null];
        }

        $items = array_slice($items, 0, 30);

        return response()->json(['items' => $items]);
    }

    public function semanticLoad(VaultSemanticService $service)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('viewAny', LeadNormalized::class);
        }
        $sourceId = request()->input('source_id')
            ?? request()->input('lead_source_id')
            ?? session('explore_source_id');

        if (!$sourceId) {
            return response()->json([
                'ok' => false,
                'message' => 'source_required',
                'data' => [
                    'locations' => [],
                    'segment' => [],
                    'niche' => [],
                    'origin' => [],
                    'anchor' => 'Brasil',
                ]
            ]);
        }

        $tenantUuid = $this->resolveTenantUuidForSemanticSource((int) $sourceId);

        return response()->json([
            'ok' => true,
            'data' => $service->load($tenantUuid, (int) $sourceId)
        ]);
    }

    public function semanticSave(Request $request, VaultSemanticService $service)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('viewAny', LeadNormalized::class);
        }
        $rawSourceId = $request->input('source_id')
            ?? $request->input('lead_source_id')
            ?? session('explore_source_id');
        $sourceId = is_numeric($rawSourceId) ? (int) $rawSourceId : 0;

        if ($sourceId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'source_required'
            ], 400);
        }

        $tenantUuid = $this->resolveTenantUuidForSemanticSource($sourceId);

        $sourceExists = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $sourceId)
            ->exists();
        if (!$sourceExists) {
            return response()->json([
                'ok' => false,
                'message' => 'source_not_found'
            ], 404);
        }

        $data = $request->validate([
            'source_id'  => ['nullable','integer'],
            'anchor'     => ['nullable','string','max:120'],
            'segment_id' => ['nullable','integer'],
            'segment_ids' => ['nullable','array'],
            'segment_ids.*' => ['integer'],
            'niche_id'   => ['nullable','integer'],
            'niche_ids' => ['nullable','array'],
            'niche_ids.*' => ['integer'],
            'origin_id'  => ['nullable','integer'],
            'locations'  => ['nullable','array'],
            'locations.*.type'   => ['required','in:city,state,country'],
            'locations.*.ref_id' => ['required','integer'],
        ]);

        $segmentIds = array_values(array_unique(array_map('intval', (array) ($data['segment_ids'] ?? []))));
        if (!$segmentIds && !empty($data['segment_id'])) {
            $segmentIds = [(int) $data['segment_id']];
        }

        $nicheIds = array_values(array_unique(array_map('intval', (array) ($data['niche_ids'] ?? []))));
        if (!$nicheIds && !empty($data['niche_id'])) {
            $nicheIds = [(int) $data['niche_id']];
        }

        $service->save($tenantUuid, $sourceId, [
            'anchor'      => $data['anchor'] ?? null,
            'segment_ids' => $segmentIds,
            'niche_ids'   => $nicheIds,
            'origin_ids'  => $data['origin_id'] ? [$data['origin_id']] : [],
            'locations'   => $data['locations'] ?? [],
        ]);

        return response()->json([
            'ok' => true,
            'data' => $service->load($tenantUuid, $sourceId),
        ]);
    }


    /* ======================================================
     | DB  (ULTRA FAST — leads_normalized)
     |  - sem joins
     |  - só índices
     |  - simples e rápido
     |  - UTF8 SAFE
     ====================================================== */

    public function db(Request $r)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('viewAny', LeadNormalized::class);
        }

        $rawQ     = trim((string) $r->input('q', ''));
        $parsedQ  = $this->parseAdvancedSearchQuery($rawQ);
        $q        = $parsedQ['free_text'] ?? '';
        $normalizeIntIds = static function ($value): array {
            $items = is_array($value) ? $value : explode(',', (string) $value);
            return array_values(array_unique(array_map(
                'intval',
                array_filter($items, static fn ($id) => is_numeric($id) && (int) $id > 0)
            )));
        };

        $segmentIds = $normalizeIntIds($r->input('segment_ids', []));
        $nicheIds   = $normalizeIntIds($r->input('niche_ids', []));
        $segment    = $r->input('segment_id');
        $niche      = $r->input('niche_id');
        $origin   = $r->input('origin_id');
        $cities   = array_values(array_filter((array) $r->input('cities', [])));
        $states   = array_values(array_filter((array) $r->input('states', [])));
        $minScore = (int) $r->input('min_score', 0);
        $idsParam = $r->input('ids');
        $sourceId = $r->input('lead_source_id')
            ?? $r->input('source_id')
            ?? $r->session()->get('explore_source_id');
        $tenantUuid = $this->resolveTenantUuidForSource($sourceId ? (int) $sourceId : null);

        if (!$segment && isset($parsedQ['segment_id'])) {
            $segment = (int) $parsedQ['segment_id'];
        }
        if (!$niche && isset($parsedQ['niche_id'])) {
            $niche = (int) $parsedQ['niche_id'];
        }
        if ($segment) {
            $segmentIds[] = (int) $segment;
        }
        if ($niche) {
            $nicheIds[] = (int) $niche;
        }
        $segmentIds = array_values(array_unique(array_filter($segmentIds, static fn ($id) => $id > 0)));
        $nicheIds   = array_values(array_unique(array_filter($nicheIds, static fn ($id) => $id > 0)));
        if (!$origin && isset($parsedQ['origin_id'])) {
            $origin = (int) $parsedQ['origin_id'];
        }
        if (empty($cities) && !empty($parsedQ['cities'])) {
            $cities = array_values(array_unique(array_map('strval', (array) $parsedQ['cities'])));
        }
        if (empty($states) && !empty($parsedQ['states'])) {
            $states = array_values(array_unique(array_map('strval', (array) $parsedQ['states'])));
        }
        if ($minScore <= 0 && isset($parsedQ['min_score'])) {
            $minScore = (int) $parsedQ['min_score'];
        }
        if (!$sourceId && isset($parsedQ['source_id'])) {
            $sourceId = (int) $parsedQ['source_id'];
        }

        $perPage = max(50, min((int) $r->input('per_page', 200), 500));


        /* ======================================================
           QUERY BASE
        ====================================================== */

        $query = LeadNormalized::query()
            ->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->select([
                'id',
                'lead_source_id',
                'name',
                'email',
                'cpf',

                // coluna real do banco
                DB::raw('phone_e164 as phone'),

                'city',
                'uf',
                'sex',
                'score',
                'extras_json',
            ]);


        /* ======================================================
           BUSCA TEXTO
        ====================================================== */

        $freeTerms = $parsedQ['free_terms'] ?? [];
        if (!$freeTerms && $q !== '') {
            $freeTerms = [$q];
        }
        if ($freeTerms) {
            foreach ($freeTerms as $term) {
                $safe = addcslashes((string) $term, '%_');
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

        $fieldTerms = $parsedQ['field_terms'] ?? [];
        if (!empty($fieldTerms['name'])) {
            foreach ($fieldTerms['name'] as $value) {
                $query->where('name', 'like', '%' . addcslashes((string) $value, '%_') . '%');
            }
        }
        if (!empty($fieldTerms['email'])) {
            foreach ($fieldTerms['email'] as $value) {
                $query->where('email', 'like', '%' . addcslashes((string) $value, '%_') . '%');
            }
        }
        if (!empty($fieldTerms['cpf'])) {
            foreach ($fieldTerms['cpf'] as $value) {
                $digits = preg_replace('/\D+/', '', (string) $value);
                if ($digits === '') {
                    continue;
                }
                $query->where('cpf', 'like', '%' . $digits . '%');
            }
        }
        if (!empty($fieldTerms['phone'])) {
            foreach ($fieldTerms['phone'] as $value) {
                $digits = preg_replace('/\D+/', '', (string) $value);
                if ($digits === '') {
                    continue;
                }
                $query->where('phone_e164', 'like', '%' . $digits . '%');
            }
        }


        /* ======================================================
           SCORE
        ====================================================== */

        if ($minScore > 0) {
            $query->where('score', '>=', $minScore);
        }


        /* ======================================================
           SEMÂNTICA
           - filtro por semântica da fonte (lead_source_semantics)
        ====================================================== */

        if ($segmentIds || $nicheIds || $origin) {
            $query->whereIn('lead_source_id', function ($q) use ($tenantUuid, $segmentIds, $nicheIds, $origin) {
                $q->select('lead_source_id')
                    ->from('lead_source_semantics');
                if ($tenantUuid) {
                    $q->where('tenant_uuid', $tenantUuid);
                }
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
                if ($origin) {
                    $q->where('origin_id', $origin);
                }
            });
        }

        if ($sourceId) $query->where('lead_source_id', $sourceId);

        /* ======================================================
           IDS (selecionados)
        ====================================================== */

        if ($idsParam) {
            $ids = is_array($idsParam)
                ? $idsParam
                : array_filter(explode(',', (string) $idsParam));
            $ids = array_values(array_filter($ids, fn($id) => is_numeric($id)));
            if ($ids) {
                $query->whereIn('id', $ids);
            } else {
                $query->whereRaw('1=0');
            }
        }


        /* ======================================================
           LOCALIZAÇÃO
        ====================================================== */

        if ($cities) $query->whereIn('city', $cities);
        if ($states) $query->whereIn('uf', $states);


        /* ======================================================
           PAGINAÇÃO
        ====================================================== */

        if ($r->input('export') === 'csv') {
            return $this->exportCsv(
                $r,
                $query,
                $sourceId ? (int) $sourceId : null,
                $tenantUuid ? (string) $tenantUuid : null
            );
        }

        $totalCount = (clone $query)->count();

        $rows = $query
            ->orderByDesc('id')
            ->simplePaginate($perPage);

        $items = $rows->items();
        if ($sourceId && Schema::hasTable('lead_overrides')) {
            $items = $this->applyOverridesToRows(
                $items,
                (string) $tenantUuid,
                (int) $sourceId
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 🔥 Grade UTF-8 SAFE RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->jsonUtf8([
            'rows'      => $items,
            'next_page' => $rows->nextPageUrl(),
            'has_more'  => $rows->hasMorePages(),
            'total'     => $totalCount,
        ]);
    }

    public function saveOverride(Request $request, GuestAuditService $guestAudit)
    {
        $this->authorize('viewAny', LeadNormalized::class);

        if (!$this->canManageOverrides()) {
            abort(403);
        }

        if (!Schema::hasTable('lead_overrides')) {
            return response()->json([
                'ok' => false,
                'message' => 'table_not_ready',
            ], 503);
        }

        $data = $request->validate([
            'lead_id' => ['required', 'integer', 'min:1'],
            'column_key' => ['required', 'string', 'max:120'],
            'value' => ['nullable', 'string', 'max:10000'],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'lead_source_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantUuid = TenantStorage::requireTenantUuid();
        $sourceId = (int) (
            $data['source_id']
            ?? $data['lead_source_id']
            ?? session('explore_source_id')
        );
        if ($sourceId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'source_required',
            ], 422);
        }

        $source = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $sourceId)
            ->first(['id', 'parent_source_id']);
        if (!$source) {
            return response()->json([
                'ok' => false,
                'message' => 'source_not_found',
            ], 404);
        }

        $leadId = (int) $data['lead_id'];
        $columnKey = trim((string) $data['column_key']);
        $newValue = array_key_exists('value', $data) ? $data['value'] : null;
        $normalizedKey = match (strtolower($columnKey)) {
            'lead', 'name', 'nome' => 'nome',
            default => $columnKey,
        };

        $resolvedSourceId = $this->resolveSourceIdForLead($tenantUuid, (int) $source->id, $leadId);
        if ($resolvedSourceId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'lead_not_found',
            ], 404);
        }

        $sourceId = $resolvedSourceId;

        $lead = LeadNormalized::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->where('id', $leadId)
            ->first(['id', 'lead_source_id', 'name', 'email', 'cpf', 'phone_e164', 'city', 'uf', 'sex', 'score', 'extras_json']);

        if (!$lead) {
            return response()->json([
                'ok' => false,
                'message' => 'lead_not_found',
            ], 404);
        }

        try {
            $storedValue = $this->normalizeValueForColumn($normalizedKey, $newValue);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $baseValue = $this->extractRowValue($lead, $normalizedKey);
        $sourceMeta = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $sourceId)
            ->first(['id', 'original_name', 'file_path', 'file_hash', 'file_size_bytes']);

        if ((string) ($storedValue ?? '') === (string) ($baseValue ?? '')) {
            LeadOverride::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('lead_source_id', $sourceId)
                ->where('lead_id', $leadId)
                ->where('column_key', $normalizedKey)
                ->delete();

            if ($sourceMeta) {
                $guestAudit->logFileEvent($request, 'edit', [
                    'lead_source_id' => (int) $sourceMeta->id,
                    'file_name' => (string) $sourceMeta->original_name,
                    'file_path' => (string) $sourceMeta->file_path,
                    'file_hash' => (string) $sourceMeta->file_hash,
                    'file_size_bytes' => (int) $sourceMeta->file_size_bytes,
                    'payload' => [
                        'lead_id' => $leadId,
                        'column_key' => $normalizedKey,
                        'mode' => 'override_reverted',
                    ],
                ]);
            }

            return response()->json([
                'ok' => true,
                'lead_id' => $leadId,
                'column_key' => $normalizedKey,
                'value' => $baseValue,
                'overridden' => false,
            ]);
        }

        LeadOverride::query()->updateOrCreate(
            [
                'tenant_uuid' => $tenantUuid,
                'lead_source_id' => $sourceId,
                'lead_id' => $leadId,
                'column_key' => $normalizedKey,
            ],
            [
                'value_text' => $storedValue,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );

        if ($sourceMeta) {
            $guestAudit->logFileEvent($request, 'edit', [
                'lead_source_id' => (int) $sourceMeta->id,
                'file_name' => (string) $sourceMeta->original_name,
                'file_path' => (string) $sourceMeta->file_path,
                'file_hash' => (string) $sourceMeta->file_hash,
                'file_size_bytes' => (int) $sourceMeta->file_size_bytes,
                'payload' => [
                    'lead_id' => $leadId,
                    'column_key' => $normalizedKey,
                    'mode' => 'override_set',
                ],
            ]);
        }

        return response()->json([
            'ok' => true,
            'lead_id' => $leadId,
            'column_key' => $normalizedKey,
            'value' => $storedValue,
            'overridden' => true,
        ]);
    }

    public function overridesSummary(Request $request)
    {
        $this->authorize('viewAny', LeadNormalized::class);
        if (!$this->canManageOverrides()) {
            abort(403);
        }

        if (!Schema::hasTable('lead_overrides')) {
            return response()->json([
                'ok' => true,
                'total' => 0,
                'items' => [],
            ]);
        }

        $tenantUuid = TenantStorage::requireTenantUuid();
        $sourceId = (int) (
            $request->input('source_id')
            ?? $request->input('lead_source_id')
            ?? session('explore_source_id')
        );
        if ($sourceId <= 0) {
            return response()->json([
                'ok' => true,
                'total' => 0,
                'items' => [],
            ]);
        }

        $source = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $sourceId)
            ->first(['id', 'parent_source_id']);
        if ($source) {
            $sourceId = $this->resolveActiveSourceIdForSelection($source, $tenantUuid);
        }

        $query = LeadOverride::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId);

        $total = (int) (clone $query)->count();
        $items = $query
            ->orderByDesc('updated_at')
            ->limit(80)
            ->get(['lead_id', 'column_key', 'value_text', 'updated_at']);

        return response()->json([
            'ok' => true,
            'total' => $total,
            'items' => $items,
        ]);
    }

    public function discardOverrides(Request $request, GuestAuditService $guestAudit)
    {
        $this->authorize('viewAny', LeadNormalized::class);
        if (!$this->canManageOverrides()) {
            abort(403);
        }

        if (!Schema::hasTable('lead_overrides')) {
            return response()->json([
                'ok' => true,
                'removed' => 0,
            ]);
        }

        $tenantUuid = TenantStorage::requireTenantUuid();
        $sourceId = (int) (
            $request->input('source_id')
            ?? $request->input('lead_source_id')
            ?? session('explore_source_id')
        );
        if ($sourceId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'source_required',
            ], 422);
        }

        $source = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $sourceId)
            ->first(['id', 'parent_source_id']);
        if ($source) {
            $sourceId = $this->resolveActiveSourceIdForSelection($source, $tenantUuid);
        }

        $removed = LeadOverride::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->delete();

        if ($removed > 0) {
            $sourceMeta = LeadSource::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('id', $sourceId)
                ->first(['id', 'original_name', 'file_path', 'file_hash', 'file_size_bytes']);
            if ($sourceMeta) {
                $guestAudit->logFileEvent($request, 'discard', [
                    'lead_source_id' => (int) $sourceMeta->id,
                    'file_name' => (string) $sourceMeta->original_name,
                    'file_path' => (string) $sourceMeta->file_path,
                    'file_hash' => (string) $sourceMeta->file_hash,
                    'file_size_bytes' => (int) $sourceMeta->file_size_bytes,
                    'payload' => ['removed' => (int) $removed],
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'removed' => (int) $removed,
        ]);
    }

    public function publishOverrides(
        Request $request,
        LeadDataQualityService $service,
        GuestAuditService $guestAudit
    )
    {
        $this->authorize('viewAny', LeadNormalized::class);
        if (!$this->canManageOverrides()) {
            abort(403);
        }

        if (!Schema::hasTable('lead_overrides')) {
            return response()->json([
                'ok' => false,
                'message' => 'table_not_ready',
            ], 503);
        }

        $tenantUuid = TenantStorage::requireTenantUuid();
        $sourceId = (int) (
            $request->input('source_id')
            ?? $request->input('lead_source_id')
            ?? session('explore_source_id')
        );
        if ($sourceId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'source_required',
            ], 422);
        }

        $source = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $sourceId)
            ->firstOrFail();

        $hasParentSource = Schema::hasColumn('lead_sources', 'parent_source_id');
        $hasSourceKind = Schema::hasColumn('lead_sources', 'source_kind');
        $rootSourceId = $source->id;
        if ($hasParentSource && !empty($source->parent_source_id)) {
            $rootSourceId = (int) $source->parent_source_id;
        }

        $workingSource = null;
        if ($hasParentSource && !empty($source->parent_source_id)) {
            $workingSource = $source;
        } elseif ($hasParentSource) {
            $workingSource = LeadSource::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('parent_source_id', $rootSourceId)
                ->orderByDesc('id')
                ->first();

            if ($workingSource && (int) $workingSource->id !== (int) $source->id) {
                LeadOverride::query()
                    ->where('tenant_uuid', $tenantUuid)
                    ->where('lead_source_id', $sourceId)
                    ->update(['lead_source_id' => (int) $workingSource->id]);
                $source = $workingSource;
                $sourceId = (int) $workingSource->id;
            }
        }

        $pendingCount = (int) LeadOverride::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->count();
        if ($pendingCount < 1) {
            return response()->json([
                'ok' => false,
                'message' => 'no_overrides',
            ], 422);
        }

        $changedColumns = LeadOverride::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->select('column_key', DB::raw('COUNT(*) as qty'))
            ->groupBy('column_key')
            ->orderByDesc('qty')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->column_key,
                'count' => (int) $row->qty,
            ])
            ->values()
            ->all();

        $sampleWindow = 500;
        $previewLimit = 40;
        $overrideSampleRows = LeadOverride::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($sampleWindow)
            ->get(['lead_id', 'column_key', 'value_text']);

        $leadIds = $overrideSampleRows->pluck('lead_id')->filter()->unique()->values()->all();
        $normalizedByLeadId = LeadNormalized::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->when($leadIds, fn ($q) => $q->whereIn('id', $leadIds))
            ->get(['id', 'name', 'email', 'cpf', 'phone_e164', 'city', 'uf', 'sex', 'score', 'extras_json'])
            ->keyBy('id');

        $changesPreview = [];
        foreach ($overrideSampleRows as $item) {
            if (count($changesPreview) >= $previewLimit) {
                break;
            }

            $leadId = (int) $item->lead_id;
            $columnKey = (string) $item->column_key;
            $after = $item->value_text;
            $row = $normalizedByLeadId->get($leadId);
            $before = $this->valueFromNormalizedRow($row, $columnKey);

            if ((string) ($before ?? '') === (string) ($after ?? '')) {
                continue;
            }

            $changesPreview[] = [
                'lead_id' => $leadId,
                'column_key' => $columnKey,
                'before' => $before,
                'after' => $after,
            ];
        }

        $export = $service->exportSourceWithOverridesCsv($sourceId);
        $rootSourceName = $this->canonicalSourceName((string) (
            LeadSource::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('id', $rootSourceId)
                ->value('original_name')
            ?? $source->original_name
        ));
        $target = $workingSource;
        if (!$target) {
            $payload = [
                'tenant_uuid' => $tenantUuid,
                'original_name' => $rootSourceName,
                'file_path' => $export['path'],
                'file_ext' => 'csv',
                'file_size_bytes' => $export['size'],
                'file_hash' => $export['hash'],
                'status' => 'queued',
                'mapping_json' => $source->mapping_json,
                'created_by' => auth()->id(),
            ];
            if ($hasParentSource) {
                $payload['parent_source_id'] = $rootSourceId;
            }
            if ($hasSourceKind) {
                $payload['source_kind'] = 'edited';
            }
            if (Schema::hasColumn('lead_sources', 'derived_from')) {
                $payload['derived_from'] = [
                    'source_id' => $source->id,
                    'mode' => 'overrides_publish',
                    'overrides_count' => $pendingCount,
                    'changed_columns' => $changedColumns,
                    'changes_preview' => $changesPreview,
                    'output_file_path' => $export['path'],
                ];
            }

            $target = LeadSource::query()->create($payload);
        } else {
            if (Schema::hasTable('lead_raw')) {
                DB::table('lead_raw')
                    ->where('lead_source_id', (int) $target->id)
                    ->delete();
            }
            if (Schema::hasTable('leads_normalized')) {
                DB::table('leads_normalized')
                    ->where('lead_source_id', (int) $target->id)
                    ->delete();
            }

            $update = [
                'original_name' => $rootSourceName,
                'file_path' => $export['path'],
                'file_ext' => 'csv',
                'file_size_bytes' => $export['size'],
                'file_hash' => $export['hash'],
                'status' => 'queued',
                'mapping_json' => $source->mapping_json,
                'progress_percent' => 0,
                'processed_rows' => 0,
                'inserted_rows' => 0,
                'skipped_rows' => 0,
                'cancel_requested' => false,
                'started_at' => null,
                'finished_at' => null,
                'last_error' => null,
            ];
            if (Schema::hasColumn('lead_sources', 'derived_from')) {
                $update['derived_from'] = [
                    'source_id' => $source->id,
                    'mode' => 'overrides_publish',
                    'overrides_count' => $pendingCount,
                    'changed_columns' => $changedColumns,
                    'changes_preview' => $changesPreview,
                    'output_file_path' => $export['path'],
                ];
            }

            LeadSource::query()
                ->where('id', (int) $target->id)
                ->update($update);
            $target->refresh();
        }

        ImportLeadSourceJob::dispatch((int) $target->id)
            ->onQueue('imports')
            ->afterCommit();

        LeadOverride::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->delete();

        $prunedCount = $this->pruneEditedChainKeepingLatest($tenantUuid, $rootSourceId, 1);
        $target->refresh();
        session(['explore_source_id' => (int) $target->id]);

        $guestAudit->logFileEvent($request, 'publish', [
            'lead_source_id' => (int) $target->id,
            'file_name' => (string) $target->original_name,
            'file_path' => (string) $target->file_path,
            'file_hash' => (string) $target->file_hash,
            'file_size_bytes' => (int) $target->file_size_bytes,
            'payload' => [
                'source_id' => (int) $sourceId,
                'root_source_id' => (int) $rootSourceId,
                'pending_count' => (int) $pendingCount,
                'changed_columns' => $changedColumns,
            ],
        ]);

        SecuritySyncMissingJob::dispatchAsync();

        return response()->json([
            'ok' => true,
            'derived' => [
                'id' => $target->id,
                'name' => $target->original_name,
            ],
            'current' => [
                'id' => $target->id,
                'name' => $target->original_name,
            ],
            'pruned' => $prunedCount,
        ]);
    }

    private function canManageOverrides(): bool
    {
        return (bool) (
            auth()->user()?->hasPermission('leads.normalize')
            || auth()->user()?->hasPermission('system.settings')
        );
    }

    private function canonicalSourceName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $name;
        }

        $parts = pathinfo($name);
        $fileName = (string) ($parts['filename'] ?? $name);
        $ext = (string) ($parts['extension'] ?? '');

        $fileName = preg_replace('/(?:_v\d+|\s*\(\d+\))$/i', '', $fileName) ?: $fileName;
        $fileName = trim($fileName);

        return $ext !== '' ? ($fileName . '.' . $ext) : $fileName;
    }

    private function buildDerivedVersionName(string $rootOriginalName, int $version): string
    {
        $version = max(2, $version);
        $parts = pathinfo($rootOriginalName);
        $fileName = (string) ($parts['filename'] ?? $rootOriginalName);
        $ext = (string) ($parts['extension'] ?? '');
        $fileName = preg_replace('/(?:_v\d+|\s*\(\d+\))$/i', '', $fileName) ?: $fileName;
        $versioned = $fileName . ' (' . $version . ')';
        return $ext !== '' ? ($versioned . '.' . $ext) : $versioned;
    }

    private function resolveNextDerivedVersion(string $tenantUuid, int $rootSourceId, ?string $rootSourceName = null): int
    {
        $rootSourceName = (string) ($rootSourceName ?: (
            LeadSource::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('id', $rootSourceId)
                ->value('original_name')
        ));

        $base = pathinfo($rootSourceName, PATHINFO_FILENAME);
        $base = preg_replace('/(?:_v\d+|\s*\(\d+\))$/i', '', (string) $base) ?: (string) $base;
        $pattern = '/^' . preg_quote($base, '/') . '(?:_v(\d+)|\s*\((\d+)\))$/i';

        $maxVersion = 1;
        $names = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where(function ($q) use ($rootSourceId) {
                $q->where('id', $rootSourceId)
                    ->orWhere('parent_source_id', $rootSourceId);
            })
            ->pluck('original_name');

        foreach ($names as $name) {
            $filename = pathinfo((string) $name, PATHINFO_FILENAME);
            if (preg_match($pattern, (string) $filename, $m)) {
                $ver = (int) (($m[1] ?? 0) ?: ($m[2] ?? 0));
                if ($ver > $maxVersion) {
                    $maxVersion = $ver;
                }
            }
        }

        $candidate = max(2, $maxVersion + 1);
        while (LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('original_name', $this->buildDerivedVersionName($rootSourceName, $candidate))
            ->exists()) {
            $candidate++;
        }

        return $candidate;
    }

    private function resolveActiveSourceIdForSelection(LeadSource $selected, string $tenantUuid): int
    {
        $rootSourceId = !empty($selected->parent_source_id)
            ? (int) $selected->parent_source_id
            : (int) $selected->id;

        $latestEditedId = (int) LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('parent_source_id', $rootSourceId)
            ->orderByDesc('id')
            ->value('id');

        return $latestEditedId > 0 ? $latestEditedId : (int) $selected->id;
    }

    private function resolveSourceIdForLead(string $tenantUuid, int $selectedSourceId, int $leadId): int
    {
        $selected = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $selectedSourceId)
            ->first(['id', 'parent_source_id']);
        if (!$selected) {
            return 0;
        }

        $rootSourceId = !empty($selected->parent_source_id)
            ? (int) $selected->parent_source_id
            : (int) $selected->id;

        $chainIds = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where(function ($q) use ($rootSourceId) {
                $q->where('id', $rootSourceId)
                    ->orWhere('parent_source_id', $rootSourceId);
            })
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (!in_array($selectedSourceId, $chainIds, true)) {
            array_unshift($chainIds, $selectedSourceId);
        }

        foreach ($chainIds as $candidateSourceId) {
            $exists = LeadNormalized::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('lead_source_id', $candidateSourceId)
                ->where('id', $leadId)
                ->exists();
            if ($exists) {
                return (int) $candidateSourceId;
            }
        }

        return 0;
    }

    private function pruneEditedChainKeepingLatest(string $tenantUuid, int $rootSourceId, int $keep = 2): int
    {
        $keep = max(1, (int) $keep);
        $editedIds = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('parent_source_id', $rootSourceId)
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($editedIds) <= $keep) {
            $this->normalizeEditedChainNames($tenantUuid, $rootSourceId, $keep);
            return 0;
        }

        $deleteIds = array_slice($editedIds, $keep);
        if (!$deleteIds) {
            return 0;
        }

        if (Schema::hasTable('lead_overrides')) {
            LeadOverride::query()
                ->where('tenant_uuid', $tenantUuid)
                ->whereIn('lead_source_id', $deleteIds)
                ->delete();
        }

        if (Schema::hasTable('lead_raw')) {
            DB::table('lead_raw')
                ->whereIn('lead_source_id', $deleteIds)
                ->delete();
        }

        if (Schema::hasTable('leads_normalized')) {
            DB::table('leads_normalized')
                ->whereIn('lead_source_id', $deleteIds)
                ->delete();
        }

        if (Schema::hasTable('lead_column_settings')) {
            DB::table('lead_column_settings')
                ->where('tenant_uuid', $tenantUuid)
                ->whereIn('lead_source_id', $deleteIds)
                ->delete();
        }

        if (Schema::hasTable('explore_view_preferences')) {
            ExploreViewPreference::query()
                ->where('tenant_uuid', $tenantUuid)
                ->whereIn('lead_source_id', $deleteIds)
                ->delete();
        }

        $deleted = (int) LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->whereIn('id', $deleteIds)
            ->delete();

        $this->normalizeEditedChainNames($tenantUuid, $rootSourceId, $keep);

        return $deleted;
    }

    private function normalizeEditedChainNames(string $tenantUuid, int $rootSourceId, int $keep = 2): void
    {
        $keep = max(1, (int) $keep);
        $rootName = (string) (
            LeadSource::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('id', $rootSourceId)
                ->value('original_name')
        );
        if ($rootName === '') {
            return;
        }

        $edited = LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('parent_source_id', $rootSourceId)
            ->orderByDesc('id')
            ->limit($keep)
            ->get(['id', 'original_name']);

        if ($edited->isEmpty()) {
            return;
        }

        $ordered = $edited->sortBy('id')->values();
        foreach ($ordered as $index => $row) {
            $targetVersion = 2 + $index;
            $targetName = $this->buildDerivedVersionName($rootName, $targetVersion);
            if ((string) $row->original_name === $targetName) {
                continue;
            }

            $uniqueName = $this->resolveUniqueDerivedName($tenantUuid, $targetName, (int) $row->id);
            LeadSource::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('id', (int) $row->id)
                ->update(['original_name' => $uniqueName]);
        }
    }

    private function resolveUniqueDerivedName(string $tenantUuid, string $baseName, int $exceptId): string
    {
        $candidate = $baseName;
        $parts = pathinfo($baseName);
        $stem = (string) ($parts['filename'] ?? $baseName);
        $ext = (string) ($parts['extension'] ?? '');
        $suffix = 2;

        while (LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', '!=', $exceptId)
            ->where('original_name', $candidate)
            ->exists()) {
            $candidate = $ext !== ''
                ? ($stem . ' - ' . $suffix . '.' . $ext)
                : ($stem . ' - ' . $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private function parseAdvancedSearchQuery(string $input): array
    {
        $result = [
            'free_text' => '',
            'free_terms' => [],
            'field_terms' => [
                'name' => [],
                'email' => [],
                'cpf' => [],
                'phone' => [],
            ],
            'cities' => [],
            'states' => [],
        ];

        $input = trim($input);
        if ($input === '') {
            return $result;
        }

        preg_match_all('/"([^"]+)"|(\\S+)/u', $input, $matches);
        $tokens = array_values(array_filter(array_map(static function ($quoted, $plain) {
            $value = $quoted !== '' ? $quoted : $plain;
            return trim((string) $value);
        }, $matches[1] ?? [], $matches[2] ?? [])));

        foreach ($tokens as $token) {
            $pair = explode(':', $token, 2);
            if (count($pair) !== 2) {
                $result['free_terms'][] = $token;
                continue;
            }

            [$rawKey, $value] = $pair;
            $key = Str::lower(trim($rawKey));
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            switch ($key) {
                case 'nome':
                case 'name':
                    $result['field_terms']['name'][] = $value;
                    break;
                case 'email':
                    $result['field_terms']['email'][] = $value;
                    break;
                case 'cpf':
                    $result['field_terms']['cpf'][] = $value;
                    break;
                case 'tel':
                case 'fone':
                case 'telefone':
                case 'phone':
                    $result['field_terms']['phone'][] = $value;
                    break;
                case 'cidade':
                case 'city':
                    $result['cities'][] = $value;
                    break;
                case 'uf':
                case 'estado':
                case 'state':
                    $result['states'][] = Str::upper($value);
                    break;
                case 'segmento':
                case 'segment':
                case 'seg':
                    if (ctype_digit($value)) {
                        $result['segment_id'] = (int) $value;
                    }
                    break;
                case 'nicho':
                case 'niche':
                    if (ctype_digit($value)) {
                        $result['niche_id'] = (int) $value;
                    }
                    break;
                case 'origem':
                case 'origin':
                    if (ctype_digit($value)) {
                        $result['origin_id'] = (int) $value;
                    }
                    break;
                case 'arquivo':
                case 'source':
                    if (ctype_digit($value)) {
                        $result['source_id'] = (int) $value;
                    }
                    break;
                case 'score':
                    if (preg_match('/(\d{1,3})\+?/', $value, $m)) {
                        $result['min_score'] = (int) $m[1];
                    }
                    break;
                default:
                    $result['free_terms'][] = $token;
                    break;
            }
        }

        $result['cities'] = array_values(array_unique(array_filter($result['cities'])));
        $result['states'] = array_values(array_unique(array_filter($result['states'])));
        $result['free_terms'] = array_values(array_unique(array_filter($result['free_terms'])));
        $result['free_text'] = implode(' ', $result['free_terms']);

        return $result;
    }

    private function valueFromNormalizedRow(?LeadNormalized $row, string $columnKey): mixed
    {
        if (!$row) {
            return null;
        }

        return match ($columnKey) {
            'lead', 'name', 'nome' => $row->name,
            'email' => $row->email,
            'cpf' => $row->cpf,
            'phone' => $row->phone_e164,
            'city' => $row->city,
            'uf' => $row->uf,
            'sex' => $row->sex,
            'score' => $row->score,
            default => $this->valueFromExtras($row->extras_json, $columnKey),
        };
    }

    private function valueFromExtras(mixed $extras, string $columnKey): mixed
    {
        if (is_string($extras)) {
            $decoded = json_decode($extras, true);
            $extras = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($extras)) {
            return null;
        }
        return $extras[$columnKey] ?? null;
    }

    private function normalizeValueForColumn(string $columnKey, mixed $raw): ?string
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

    private function applyOverridesToRows(array $rows, string $tenantUuid, int $sourceId): array
    {
        $ids = array_values(array_filter(array_map(
            fn ($row) => (int) ($row->id ?? 0),
            $rows
        )));
        if (!$ids) {
            return $rows;
        }

        $overrides = LeadOverride::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->whereIn('lead_id', $ids)
            ->get(['lead_id', 'column_key', 'value_text'])
            ->groupBy('lead_id');

        foreach ($rows as $row) {
            $leadId = (int) ($row->id ?? 0);
            $leadOverrides = $overrides->get($leadId);
            if (!$leadOverrides instanceof Collection || $leadOverrides->isEmpty()) {
                continue;
            }

            $extras = $this->decodeExtrasJson($row->extras_json ?? null);
            foreach ($leadOverrides as $override) {
                $key = (string) $override->column_key;
                $value = $override->value_text;
                $applied = false;
                switch ($key) {
                    case 'nome':
                case 'lead':
                        $row->name = $value;
                        $applied = true;
                        break;
                    case 'email':
                        $row->email = $value;
                        $applied = true;
                        break;
                    case 'cpf':
                        $row->cpf = $value;
                        $applied = true;
                        break;
                    case 'phone':
                        $row->phone = $value;
                        $row->phone_e164 = $value;
                        $applied = true;
                        break;
                    case 'city':
                        $row->city = $value;
                        $applied = true;
                        break;
                    case 'uf':
                        $row->uf = $value;
                        $applied = true;
                        break;
                    case 'sex':
                        $row->sex = $value;
                        $applied = true;
                        break;
                    case 'score':
                        $row->score = is_numeric($value) ? (float) $value : $value;
                        $applied = true;
                        break;
                }

                if (!$applied) {
                    if ($value === null) {
                        unset($extras[$key]);
                    } else {
                        $extras[$key] = $value;
                    }
                }
            }

            $row->extras_json = $extras ? json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
        }

        return $rows;
    }

    private function extractRowValue(LeadNormalized $lead, string $columnKey): ?string
    {
        return match ($columnKey) {
            'nome' => $lead->name,
            'lead' => $lead->name,
            'email' => $lead->email,
            'cpf' => $lead->cpf,
            'phone' => $lead->phone_e164,
            'city' => $lead->city,
            'uf' => $lead->uf,
            'sex' => $lead->sex,
            'score' => $lead->score !== null ? (string) $lead->score : null,
            default => $this->decodeExtrasJson($lead->extras_json)[$columnKey] ?? null,
        };
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

    private function exportCsv(Request $r, $query, ?int $sourceId = null, ?string $tenantUuid = null)
    {
        $colsParam = trim((string) $r->input('cols', ''));
        $selected  = $colsParam !== '' ? array_values(array_filter(explode(',', $colsParam))) : [];
        $normalizedSelected = [];
        foreach ($selected as $col) {
            $key = in_array($col, ['lead', 'name'], true) ? 'nome' : $col;
            if (!in_array($key, $normalizedSelected, true)) {
                $normalizedSelected[] = $key;
            }
        }

        $baseMap = [
            'nome'  => 'name',
            'lead'  => 'name',
            'name'  => 'name',
            'email' => 'email',
            'cpf'   => 'cpf',
            'phone' => 'phone',
            'city'  => 'city',
            'uf'    => 'uf',
            'sex'   => 'sex',
            'score' => 'score',
        ];

        $baseSelected = [];
        $extraSelected = [];

        if ($normalizedSelected) {
            foreach ($normalizedSelected as $col) {
                if (isset($baseMap[$col])) {
                    $baseSelected[$col] = $baseMap[$col];
                } else {
                    $extraSelected[] = $col;
                }
            }
        } else {
            $baseSelected = [
                'nome'  => 'name',
                'email' => 'email',
                'cpf'   => 'cpf',
                'phone' => 'phone',
                'city'  => 'city',
                'uf'    => 'uf',
                'sex'   => 'sex',
                'score' => 'score',
            ];
        }

        $extraKeys = $extraSelected;

        if (!$normalizedSelected) {
            $extraKeys = $this->collectExtraKeys(clone $query);
        }

        $select = [];
        foreach (array_values(array_unique($baseSelected)) as $col) {
            if ($col === 'phone') {
                $select[] = DB::raw('phone_e164 as phone');
                continue;
            }
            $select[] = $col;
        }
        $select[] = 'id';
        $select[] = 'lead_source_id';
        $select[] = 'extras_json';

        $fileName = 'registros-' . now()->format('Ymd-His') . '.csv';
        $sourceMeta = null;
        if ($tenantUuid && $sourceId) {
            $sourceMeta = LeadSource::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('id', (int) $sourceId)
                ->first(['id', 'original_name', 'file_path', 'file_hash', 'file_size_bytes']);
        }
        app(GuestAuditService::class)->logFileEvent($r, 'export', [
            'lead_source_id' => (int) ($sourceMeta->id ?? $sourceId ?? 0),
            'file_name' => (string) ($sourceMeta->original_name ?? $fileName),
            'file_path' => (string) ($sourceMeta->file_path ?? ''),
            'file_hash' => (string) ($sourceMeta->file_hash ?? ''),
            'file_size_bytes' => (int) ($sourceMeta->file_size_bytes ?? 0),
            'payload' => [
                'export_file_name' => $fileName,
                'source_id' => $sourceId ? (int) $sourceId : null,
                'columns' => array_keys($baseSelected),
                'extra_columns' => $extraKeys,
            ],
        ]);

        return response()->streamDownload(function () use ($query, $select, $baseSelected, $extraKeys, $tenantUuid, $sourceId) {
            $out = fopen('php://output', 'w');

            $headers = array_merge(array_keys($baseSelected), $extraKeys);
            fputcsv($out, $headers, ';');

            $buffer = [];
            $flushBuffer = function () use (&$buffer, $out, $baseSelected, $extraKeys, $tenantUuid, $sourceId) {
                if (!$buffer) {
                    return;
                }

                if ($tenantUuid && Schema::hasTable('lead_overrides')) {
                    $buffer = $sourceId
                        ? $this->applyOverridesToRows($buffer, $tenantUuid, $sourceId)
                        : $this->applyOverridesToRowsMultiSource($buffer, $tenantUuid);
                }

                foreach ($buffer as $row) {
                    $rowData = [];
                    foreach ($baseSelected as $dbCol) {
                        $rowData[] = data_get($row, $dbCol);
                    }

                    $extras = $this->decodeExtrasJson($row->extras_json ?? null);
                    foreach ($extraKeys as $key) {
                        $value = $extras[$key] ?? null;
                        if (is_array($value) || is_object($value)) {
                            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                        }
                        $rowData[] = $value;
                    }

                    fputcsv($out, $rowData, ';');
                }

                $buffer = [];
            };

            $query->select($select)->orderByDesc('id')->cursor()->each(function ($row) use (&$buffer, $flushBuffer) {
                $buffer[] = $row;
                if (count($buffer) >= 500) {
                    $flushBuffer();
                }
            });

            $flushBuffer();

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function applyOverridesToRowsMultiSource(array $rows, string $tenantUuid): array
    {
        $ids = [];
        $sourceIds = [];
        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            $sid = (int) ($row->lead_source_id ?? 0);
            if ($id > 0) $ids[] = $id;
            if ($sid > 0) $sourceIds[] = $sid;
        }
        $ids = array_values(array_unique($ids));
        $sourceIds = array_values(array_unique($sourceIds));
        if (!$ids || !$sourceIds) {
            return $rows;
        }

        $all = LeadOverride::query()
            ->where('tenant_uuid', $tenantUuid)
            ->whereIn('lead_source_id', $sourceIds)
            ->whereIn('lead_id', $ids)
            ->get(['lead_source_id', 'lead_id', 'column_key', 'value_text']);

        $map = [];
        foreach ($all as $override) {
            $k = $override->lead_source_id . ':' . $override->lead_id;
            $map[$k][] = $override;
        }

        foreach ($rows as $row) {
            $k = ((int) ($row->lead_source_id ?? 0)) . ':' . ((int) ($row->id ?? 0));
            $leadOverrides = $map[$k] ?? null;
            if (!$leadOverrides) {
                continue;
            }

            $extras = $this->decodeExtrasJson($row->extras_json ?? null);
            foreach ($leadOverrides as $override) {
                $key = (string) $override->column_key;
                $value = $override->value_text;
                $applied = false;
                switch ($key) {
                    case 'nome':
                case 'lead':
                        $row->name = $value;
                        $applied = true;
                        break;
                    case 'email':
                        $row->email = $value;
                        $applied = true;
                        break;
                    case 'cpf':
                        $row->cpf = $value;
                        $applied = true;
                        break;
                    case 'phone':
                        $row->phone = $value;
                        $row->phone_e164 = $value;
                        $applied = true;
                        break;
                    case 'city':
                        $row->city = $value;
                        $applied = true;
                        break;
                    case 'uf':
                        $row->uf = $value;
                        $applied = true;
                        break;
                    case 'sex':
                        $row->sex = $value;
                        $applied = true;
                        break;
                    case 'score':
                        $row->score = is_numeric($value) ? (float) $value : $value;
                        $applied = true;
                        break;
                }

                if (!$applied) {
                    if ($value === null) {
                        unset($extras[$key]);
                    } else {
                        $extras[$key] = $value;
                    }
                }
            }

            $row->extras_json = $extras ? json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
        }

        return $rows;
    }

    private function collectExtraKeys($query): array
    {
        $keys = [];

        $query->select(['extras_json'])->orderByDesc('id')->cursor()->each(function ($row) use (&$keys) {
            $extras = $row->extras_json;
            if (is_string($extras)) {
                $decoded = json_decode($extras, true);
                $extras = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($extras)) $extras = [];

            foreach ($extras as $k => $v) {
                $keys[$k] = true;
            }
        });

        return array_keys($keys);
    }


}
