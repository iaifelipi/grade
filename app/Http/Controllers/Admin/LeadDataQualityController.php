<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExploreViewPreference;
use App\Models\LeadColumnSetting;
use App\Models\LeadOverride;
use App\Models\LeadNormalized;
use App\Models\LeadSource;
use App\Services\LeadDataQualityService;
use App\Services\LeadsVault\LeadSourcePurgeService;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use App\Jobs\ImportLeadSourceJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadDataQualityController extends Controller
{
    private function isGlobalSuperAdminContext(): bool
    {
        return auth()->check()
            && auth()->user()?->isSuperAdmin()
            && !session()->has('impersonate_user_id');
    }

    private function applyTenantContext(string $tenantUuid): void
    {
        if (trim($tenantUuid) === '') {
            return;
        }

        session(['tenant_uuid_override' => $tenantUuid]);
        app()->instance('tenant_uuid', $tenantUuid);
        view()->share('tenant_uuid', $tenantUuid);
    }

    private function currentTenantUuid(): string
    {
        if (app()->bound('tenant_uuid')) {
            $uuid = trim((string) app('tenant_uuid'));
            if ($uuid !== '') {
                return $uuid;
            }
        }

        return TenantStorage::requireTenantUuid();
    }

    private function sourceQuery(?string $tenantUuid = null)
    {
        if ($this->isGlobalSuperAdminContext()) {
            return LeadSource::withoutGlobalScopes()
                ->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid));
        }

        return LeadSource::query()
            ->where('tenant_uuid', $tenantUuid ?: $this->currentTenantUuid());
    }

    public function index(Request $request)
    {
        return view('admin.data-quality.index', $this->buildPageData($request));
    }

    public function modal(Request $request)
    {
        return view('admin.data-quality.panel', array_merge(
            $this->buildPageData($request),
            ['embedded' => true]
        ));
    }

    public function preview(Request $request, LeadDataQualityService $service)
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'source_id' => ['required', 'integer'],
            'column_key' => ['required', 'string', 'max:120'],
            'rules' => ['nullable', 'array'],
            'rules.*' => ['string'],
        ]);

        $sourceId = (int) $data['source_id'];
        $source = $this->sourceQuery()
            ->where('id', $sourceId)
            ->firstOrFail(['id', 'tenant_uuid']);
        $tenantUuid = (string) ($source->tenant_uuid ?: $this->currentTenantUuid());

        if ($this->isGlobalSuperAdminContext()) {
            $this->applyTenantContext($tenantUuid);
        }

        $rows = LeadNormalized::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'name', 'email', 'cpf', 'phone_e164', 'city', 'uf', 'sex', 'score', 'extras_json']);

        $rows = $service->applyOverridesToNormalizedRows($rows, $sourceId, $tenantUuid);

        $rules = $data['rules'] ?? [];

        $items = $rows->map(function ($row) use ($service, $data, $rules) {
            $before = $service->getValue($row, $data['column_key']);
            $after = $service->applyRules($before, $rules);
            return [
                'id' => $row->id,
                'before' => $before,
                'after' => $after,
            ];
        });

        return response()->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    public function statuses(Request $request)
    {
        $this->authorizeAccess();

        $hasParentSource = Schema::hasColumn('lead_sources', 'parent_source_id');
        if (!$hasParentSource) {
            return response()->json([
                'ok' => true,
                'items' => [],
            ]);
        }

        $sourceId = (int) ($request->query('source_id') ?? session('explore_source_id'));
        if ($sourceId <= 0) {
            return response()->json([
                'ok' => true,
                'items' => [],
            ]);
        }

        $rootSourceId = $sourceId;
        $selected = $this->sourceQuery()
            ->where('id', $sourceId)
            ->first(['id', 'tenant_uuid', 'parent_source_id']);
        if (!$selected) {
            return response()->json([
                'ok' => true,
                'items' => [],
            ]);
        }

        $tenantUuid = (string) ($selected->tenant_uuid ?: $this->currentTenantUuid());
        if ($this->isGlobalSuperAdminContext()) {
            $this->applyTenantContext($tenantUuid);
        }
        if ($selected && !empty($selected->parent_source_id)) {
            $rootSourceId = (int) $selected->parent_source_id;
        }

        $items = $this->sourceQuery($tenantUuid)
            ->where('tenant_uuid', $tenantUuid)
            ->where('parent_source_id', $rootSourceId)
            ->orderByDesc('id')
            ->limit(2)
            ->get(['id', 'status'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'status' => (string) ($row->status ?? 'unknown'),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    public function apply(Request $request, LeadDataQualityService $service)
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'source_id' => ['required', 'integer'],
            'column_key' => ['required', 'string', 'max:120'],
            'rules' => ['nullable', 'array'],
            'rules.*' => ['string'],
        ]);

        $sourceId = (int) $data['source_id'];

        $source = $this->sourceQuery()
            ->where('id', $sourceId)
            ->firstOrFail();
        $tenantUuid = (string) ($source->tenant_uuid ?: $this->currentTenantUuid());
        if ($this->isGlobalSuperAdminContext()) {
            $this->applyTenantContext($tenantUuid);
        }

        $hasParentSource = Schema::hasColumn('lead_sources', 'parent_source_id');
        $hasSourceKind = Schema::hasColumn('lead_sources', 'source_kind');

        $rootSourceId = $source->id;
        if ($hasParentSource && !empty($source->parent_source_id)) {
            $rootSourceId = (int) $source->parent_source_id;
        }

        $rules = $data['rules'] ?? [];
        if (!$rules) {
            return back()->with('status', 'Selecione ao menos uma regra.');
        }

        $export = $service->exportEditedCsv($sourceId, $data['column_key'], $rules);
        $rootSourceName = $this->canonicalSourceName((string) (
            $this->sourceQuery($tenantUuid)
                ->where('tenant_uuid', $tenantUuid)
                ->where('id', $rootSourceId)
                ->value('original_name')
            ?? $source->original_name
        ));
        $newName = $rootSourceName;

        $payload = [
            'tenant_uuid'     => $tenantUuid,
            'original_name'   => $newName,
            'file_path'       => $export['path'],
            'file_ext'        => 'csv',
            'file_size_bytes' => $export['size'],
            'file_hash'       => $export['hash'],
            'status'          => 'queued',
            'mapping_json'    => $source->mapping_json,
            'created_by'      => auth()->id(),
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
                'column_key' => $data['column_key'],
                'rules' => $rules,
                'output_file_path' => $export['path'],
            ];
        }

        $derived = LeadSource::create($payload);

        ImportLeadSourceJob::dispatch($derived->id)
            ->onQueue('imports')
            ->afterCommit();

        if ($hasParentSource) {
            $this->pruneEditedChainKeepingLatest($tenantUuid, $rootSourceId, 2);
            $derived->refresh();
        }
        session(['explore_source_id' => (int) $derived->id]);

        return back()->with('status', 'Versão editada criada. Importação em andamento.')
            ->with('dq_job', [
                'id' => $derived->id,
                'name' => $derived->original_name,
            ]);
    }

    public function discard(int $id, LeadSourcePurgeService $purgeService)
    {
        $this->authorizeAccess();

        $hasSourceKind = Schema::hasColumn('lead_sources', 'source_kind');
        $hasParentSource = Schema::hasColumn('lead_sources', 'parent_source_id');

        $columns = ['id', 'tenant_uuid'];
        if ($hasSourceKind) {
            $columns[] = 'source_kind';
        }
        if ($hasParentSource) {
            $columns[] = 'parent_source_id';
        }

        $source = $this->sourceQuery()
            ->where('id', $id)
            ->firstOrFail($columns);
        $tenantUuid = (string) ($source->tenant_uuid ?: $this->currentTenantUuid());
        if ($this->isGlobalSuperAdminContext()) {
            $this->applyTenantContext($tenantUuid);
        }

        $isEdited = false;
        if ($hasSourceKind) {
            $isEdited = $source->source_kind === 'edited';
        } elseif ($hasParentSource) {
            $isEdited = !empty($source->parent_source_id);
        }

        if (!$isEdited) {
            return back()->with('status', 'Apenas versões editadas podem ser descartadas.');
        }

        $purgeService->purgeSelected([$source->id]);

        return back()->with('status', 'Versão editada removida.');
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

    private function authorizeAccess(): void
    {
        if (!auth()->user()?->hasPermission('system.settings')) {
            abort(403);
        }
    }

    private function buildPageData(Request $request): array
    {
        $this->authorizeAccess();

        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $tenantUuid = $isGlobalSuper ? null : $this->currentTenantUuid();
        $hasSourceKind = Schema::hasColumn('lead_sources', 'source_kind');
        $hasParentSource = Schema::hasColumn('lead_sources', 'parent_source_id');
        $hasDerivedFrom = Schema::hasColumn('lead_sources', 'derived_from');
        $sourceId = $request->query('source_id');
        if ($sourceId !== null) {
            $sourceId = (int) $sourceId;
            if ($sourceId > 0) {
                session(['explore_source_id' => $sourceId]);
            } else {
                session()->forget('explore_source_id');
            }
        }

        $sourceId = session('explore_source_id');
        $sourceColumns = ['id', 'original_name', 'status', 'created_at'];
        if ($isGlobalSuper) {
            $sourceColumns[] = 'tenant_uuid';
        }
        if ($hasSourceKind) {
            $sourceColumns[] = 'source_kind';
        }
        if ($hasParentSource) {
            $sourceColumns[] = 'parent_source_id';
        }

        $allSources = $this->sourceQuery($tenantUuid)
            ->orderByDesc('id')
            ->get($sourceColumns);
        $byId = $allSources->keyBy('id');
        [$rootsById, $latestByRoot] = $this->buildSourceChains($allSources);

        if ($sourceId) {
            $selected = $byId->get((int) $sourceId);
            if ($selected) {
                $rootId = !empty($selected->parent_source_id)
                    ? (int) $selected->parent_source_id
                    : (int) $selected->id;
                $resolvedCurrent = isset($latestByRoot[$rootId]) ? (int) $latestByRoot[$rootId]->id : (int) $sourceId;
                if ($resolvedCurrent !== (int) $sourceId) {
                    session(['explore_source_id' => $resolvedCurrent]);
                    $sourceId = $resolvedCurrent;
                }
            } else {
                session()->forget('explore_source_id');
                $sourceId = null;
            }
        }

        $currentSource = null;
        if ($sourceId) {
            $currentSource = $byId->get((int) $sourceId);
            if ($isGlobalSuper && $currentSource && !empty($currentSource->tenant_uuid)) {
                $this->applyTenantContext((string) $currentSource->tenant_uuid);
                $tenantUuid = (string) $currentSource->tenant_uuid;
            }
        }

        $rootSourceId = null;
        if ($sourceId && $currentSource) {
            $rootSourceId = (int) $sourceId;
            if ($hasParentSource && !empty($currentSource->parent_source_id)) {
                $rootSourceId = (int) $currentSource->parent_source_id;
            }
        }

        $sources = collect($latestByRoot)
            ->map(function ($active, $rootId) use ($rootsById, $isGlobalSuper) {
                $root = $rootsById[$rootId] ?? $active;
                $label = (string) ($root->original_name ?? $active->original_name);
                if ($isGlobalSuper && !empty($active->tenant_uuid)) {
                    $label .= ' · ' . (string) $active->tenant_uuid;
                }
                $active->display_name = $label;
                $active->root_source_id = (int) $rootId;
                $active->chain_role = 'current';
                return $active;
            })
            ->sortByDesc('id')
            ->values();

        $settings = collect();
        if ($sourceId) {
            $settingsSourceId = $sourceId;
            if ($currentSource && isset($currentSource->source_kind, $currentSource->parent_source_id)) {
                if ($currentSource->source_kind === 'edited' && $currentSource->parent_source_id) {
                    $settingsSourceId = (int) $currentSource->parent_source_id;
                }
            }
            $settingsTenantUuid = (string) ($currentSource->tenant_uuid ?? $tenantUuid ?? $this->currentTenantUuid());
            $settings = LeadColumnSetting::query()
                ->where('tenant_uuid', $settingsTenantUuid)
                ->where('lead_source_id', $settingsSourceId)
                ->orderBy('sort_order')
                ->get(['column_key', 'label']);
        }

        $derivedSources = collect();
        if ($rootSourceId && $hasParentSource) {
            $derivedColumns = ['id', 'original_name', 'status', 'created_at', 'file_path', 'parent_source_id'];
            if ($hasDerivedFrom) {
                $derivedColumns[] = 'derived_from';
            }
            $derivedSources = $this->sourceQuery((string) ($tenantUuid ?? ''))
                ->where('tenant_uuid', $tenantUuid)
                ->where('parent_source_id', $rootSourceId)
                ->orderByDesc('id')
                ->limit(2)
                ->get($derivedColumns);
        }

        $baseColumns = collect([
            ['key' => 'nome', 'label' => 'Nome'],
            ['key' => 'cpf', 'label' => 'CPF'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'phone', 'label' => 'Telefone'],
            ['key' => 'data_nascimento', 'label' => 'Data de nascimento'],
            ['key' => 'city', 'label' => 'Cidade'],
            ['key' => 'uf', 'label' => 'UF'],
            ['key' => 'sex', 'label' => 'Sexo'],
        ]);

        $columns = $baseColumns->map(fn ($c) => [
            'key' => $c['key'],
            'label' => $c['label'],
            'type' => 'base',
        ])->concat(
            $settings->map(fn ($s) => [
                'key' => $s->column_key,
                'label' => $s->label ?: $s->column_key,
                'type' => 'extra',
            ])
        )->unique('key')->values();

        return [
            'sources' => $sources,
            'currentSource' => $currentSource,
            'sourceId' => $sourceId,
            'rootSourceId' => $rootSourceId,
            'columns' => $columns,
            'derivedSources' => $derivedSources,
            'embedded' => false,
        ];
    }

    private function buildSourceChains($allSources): array
    {
        $byId = $allSources->keyBy('id');
        $rootsById = [];
        $latestByRoot = [];

        foreach ($allSources as $item) {
            $rootId = !empty($item->parent_source_id)
                ? (int) $item->parent_source_id
                : (int) $item->id;

            if (!isset($rootsById[$rootId])) {
                $root = $byId->get($rootId);
                $rootsById[$rootId] = $root ?: $item;
            }

            if (!isset($latestByRoot[$rootId]) || (int) $item->id > (int) $latestByRoot[$rootId]->id) {
                $latestByRoot[$rootId] = $item;
            }
        }

        return [$rootsById, $latestByRoot];
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
}
