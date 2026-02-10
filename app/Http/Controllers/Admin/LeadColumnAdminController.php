<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadColumnSetting;
use App\Models\LeadOverride;
use App\Models\LeadNormalized;
use App\Models\LeadSource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadColumnAdminController extends Controller
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

    private function sourceQuery(?string $tenantUuid = null)
    {
        if ($this->isGlobalSuperAdminContext()) {
            return LeadSource::withoutGlobalScopes()
                ->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid));
        }

        return LeadSource::query()
            ->where('tenant_uuid', $tenantUuid ?: $this->tenantUuid());
    }

    public function index()
    {
        return view('admin.columns.index', $this->buildPageData(false));
    }

    public function modal()
    {
        return view('admin.columns.panel', $this->buildPageData(true));
    }

    private function buildPageData(bool $embedded = false): array
    {
        $this->authorizeAccess();

        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $tenantUuid = $this->tenantUuid();
        $hasParentSource = Schema::hasColumn('lead_sources', 'parent_source_id');
        $hasSourceKind = Schema::hasColumn('lead_sources', 'source_kind');
        $sourceId = session('explore_source_id');
        $currentSource = null;
        if ($sourceId) {
            $columns = ['id', 'original_name'];
            if ($hasParentSource) {
                $columns[] = 'parent_source_id';
            }
            if ($hasSourceKind) {
                $columns[] = 'source_kind';
            }
            if ($isGlobalSuper) {
                $columns[] = 'tenant_uuid';
            }
            $currentSource = $this->sourceQuery($isGlobalSuper ? null : $tenantUuid)
                ->where('id', $sourceId)
                ->first($columns);
            if ($isGlobalSuper && $currentSource && !empty($currentSource->tenant_uuid)) {
                $tenantUuid = (string) $currentSource->tenant_uuid;
                $this->applyTenantContext($tenantUuid);
            }
        }

        if ($sourceId) {
            $this->ensureDefaults($sourceId);
            $this->ensureExtras($sourceId);
        }

        $sourceColumns = ['id', 'original_name'];
        if ($hasParentSource) {
            $sourceColumns[] = 'parent_source_id';
        }
        if ($hasSourceKind) {
            $sourceColumns[] = 'source_kind';
        }
        if ($isGlobalSuper) {
            $sourceColumns[] = 'tenant_uuid';
        }

        $rootSourceId = null;
        if ($sourceId && $currentSource) {
            $rootSourceId = (int) $sourceId;
            if ($hasParentSource && !empty($currentSource->parent_source_id)) {
                $rootSourceId = (int) $currentSource->parent_source_id;
            }
        }

        $sources = collect();
        if ($rootSourceId) {
            $original = $this->sourceQuery($tenantUuid)
                ->where('id', $rootSourceId)
                ->first($sourceColumns);

            $derived = collect();
            if ($hasParentSource) {
                $derived = $this->sourceQuery($tenantUuid)
                    ->where('parent_source_id', $rootSourceId)
                    ->orderByDesc('id')
                    ->get($sourceColumns);
            }

            $sources = collect([$original])
                ->filter()
                ->concat($derived)
                ->unique('id')
                ->values();
        } else {
            $sources = $this->sourceQuery($isGlobalSuper ? null : $tenantUuid)
                ->when($hasParentSource, fn ($q) => $q->whereNull('parent_source_id'))
                ->orderByDesc('id')
                ->get($sourceColumns);
        }

        if ($isGlobalSuper) {
            $sources = $sources->map(function ($source) {
                if (!empty($source->tenant_uuid)) {
                    $source->original_name = (string) $source->original_name . ' · ' . (string) $source->tenant_uuid;
                }
                return $source;
            });
        }

        $settings = LeadColumnSetting::query()
            ->where('tenant_uuid', $tenantUuid)
            ->when(
                $sourceId,
                fn ($q) => $q->where('lead_source_id', $sourceId),
                fn ($q) => $q->whereNull('lead_source_id')
            )
            ->orderByRaw('CASE WHEN group_name IS NULL THEN 1 ELSE 0 END')
            ->orderBy('group_name')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return [
            'settings' => $settings,
            'currentSource' => $currentSource,
            'sourceId' => $sourceId,
            'sources' => $sources,
            'embedded' => $embedded,
        ];
    }

    public function selectSource(int $id)
    {
        $this->authorizeAccess();
        $source = $this->sourceQuery()
            ->where('id', $id)
            ->firstOrFail(['id', 'tenant_uuid', 'parent_source_id']);
        $tenantUuid = (string) ($source->tenant_uuid ?: $this->tenantUuid());
        if ($this->isGlobalSuperAdminContext()) {
            $this->applyTenantContext($tenantUuid);
        }

        $resolvedId = $this->resolveActiveSourceId((int) $source->id, (int) ($source->parent_source_id ?? 0), $tenantUuid);
        session(['explore_source_id' => $resolvedId]);

        return redirect()->route('explore.columns.index');
    }

    public function clearSource()
    {
        $this->authorizeAccess();
        session()->forget('explore_source_id');
        return redirect()->route('explore.columns.index');
    }

    public function data(Request $request)
    {
        $this->authorizeAccess();
        $tenantUuid = $this->tenantUuid();
        $sourceId = $request->query('source_id');

        if ($sourceId) {
            $sourceId = (int) $sourceId;
            $source = $this->sourceQuery()
                ->where('id', $sourceId)
                ->first(['id', 'tenant_uuid', 'parent_source_id']);
            if ($source) {
                $tenantUuid = (string) ($source->tenant_uuid ?: $tenantUuid);
                if ($this->isGlobalSuperAdminContext()) {
                    $this->applyTenantContext($tenantUuid);
                }
                $resolvedId = $this->resolveActiveSourceId((int) $source->id, (int) ($source->parent_source_id ?? 0), $tenantUuid);
                session(['explore_source_id' => $resolvedId]);
            }
        } else {
            session()->forget('explore_source_id');
        }

        $sourceId = session('explore_source_id');
        $currentSource = null;
        if ($sourceId) {
            $currentSource = $this->sourceQuery($this->isGlobalSuperAdminContext() ? null : $tenantUuid)
                ->where('id', $sourceId)
                ->first(['id', 'original_name', 'tenant_uuid']);
            if ($this->isGlobalSuperAdminContext() && $currentSource && !empty($currentSource->tenant_uuid)) {
                $tenantUuid = (string) $currentSource->tenant_uuid;
                $this->applyTenantContext($tenantUuid);
            }
        }

        if ($sourceId) {
            $this->ensureDefaults($sourceId);
            $this->ensureExtras($sourceId);
        }

        $settings = LeadColumnSetting::query()
            ->where('tenant_uuid', $tenantUuid)
            ->when(
                $sourceId,
                fn ($q) => $q->where('lead_source_id', $sourceId),
                fn ($q) => $q->whereNull('lead_source_id')
            )
            ->orderByRaw('CASE WHEN group_name IS NULL THEN 1 ELSE 0 END')
            ->orderBy('group_name')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $html = view('admin.columns.partials.content', [
            'settings' => $settings,
            'currentSource' => $currentSource,
            'sourceId' => $sourceId,
        ])->render();

        return response()->json([
            'html' => $html,
            'current_source' => $currentSource ? [
                'id' => $currentSource->id,
                'name' => $currentSource->original_name,
            ] : null,
            'has_source' => (bool) $currentSource,
        ]);
    }

    private function resolveActiveSourceId(int $sourceId, int $parentSourceId, string $tenantUuid): int
    {
        $rootSourceId = $parentSourceId > 0 ? $parentSourceId : $sourceId;

        $latestEditedId = (int) LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('parent_source_id', $rootSourceId)
            ->orderByDesc('id')
            ->value('id');

        return $latestEditedId > 0 ? $latestEditedId : $sourceId;
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();
        $sourceId = session('explore_source_id');
        if (!$sourceId) {
            return back()->with('status', 'Selecione um arquivo no Explore.');
        }

        $data = $request->validate([
            'column_key' => ['required', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:120'],
            'group_name' => ['nullable', 'string', 'max:120'],
            'merge_rule' => ['nullable', 'in:fallback,concat'],
            'visible' => ['nullable', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $key = trim($data['column_key']);
        if ($key === '') {
            return back()->with('status', 'Chave inválida.');
        }

        $exists = LeadColumnSetting::query()
            ->where('tenant_uuid', $this->tenantUuid())
            ->where('lead_source_id', $sourceId)
            ->where('column_key', $key)
            ->exists();

        if ($exists) {
            return back()->with('status', 'Coluna já existe.');
        }

        LeadColumnSetting::create([
            'tenant_uuid' => $this->tenantUuid(),
            'lead_source_id' => $sourceId,
            'column_key' => $key,
            'label' => $data['label'] ?? null,
            'group_name' => $data['group_name'] ?? null,
            'merge_rule' => $data['merge_rule'] ?? null,
            'visible' => ($data['visible'] ?? '1') === '1',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('status', 'Coluna criada.');
    }

    public function resetDefaults()
    {
        $this->authorizeAccess();
        $sourceId = session('explore_source_id');
        if (!$sourceId) {
            return back()->with('status', 'Selecione um arquivo no Explore.');
        }

        LeadColumnSetting::query()
            ->where('tenant_uuid', $this->tenantUuid())
            ->where('lead_source_id', $sourceId)
            ->delete();

        $this->ensureDefaults($sourceId);
        $this->ensureExtras($sourceId);

        return back()->with('status', 'Padrão restaurado.');
    }

    public function save(Request $request)
    {
        $this->authorizeAccess();
        $sourceId = session('explore_source_id');
        if (!$sourceId) {
            return back()->with('status', 'Selecione um arquivo no Explore.');
        }

        $items = $request->input('items', []);
        if (!is_array($items)) {
            return back()->with('status', 'Dados inválidos.');
        }

        foreach ($items as $id => $payload) {
            if (!is_numeric($id)) continue;
            $setting = LeadColumnSetting::query()
                ->where('tenant_uuid', $this->tenantUuid())
                ->where('lead_source_id', $sourceId)
                ->find($id);
            if (!$setting) continue;

            $label = isset($payload['label']) ? trim((string) $payload['label']) : null;
            $group = isset($payload['group_name']) ? trim((string) $payload['group_name']) : null;
            $merge = $payload['merge_rule'] ?? null;
            if (!in_array($merge, [null, 'fallback', 'concat'], true)) {
                $merge = null;
            }

            $setting->update([
                'label' => $label !== '' ? $label : null,
                'group_name' => $group !== '' ? $group : null,
                'merge_rule' => $merge,
                'visible' => isset($payload['visible']) && (string) $payload['visible'] === '1',
                'sort_order' => (int) ($payload['sort_order'] ?? 0),
            ]);
        }

        return back()->with('status', 'Alterações salvas.');
    }

    public function destroy(int $id)
    {
        $this->authorizeAccess();
        $sourceId = session('explore_source_id');
        if (!$sourceId) {
            return back()->with('status', 'Selecione um arquivo no Explore.');
        }

        $setting = LeadColumnSetting::query()
            ->where('tenant_uuid', $this->tenantUuid())
            ->where('lead_source_id', $sourceId)
            ->find($id);

        if (!$setting) {
            return back()->with('status', 'Coluna não encontrada.');
        }

        if ($this->isBaseKey($setting->column_key)) {
            return back()->with('status', 'Só colunas extras podem ser excluídas.');
        }

        $columnKey = $setting->column_key;
        $setting->delete();
        $updated = $this->removeExtrasKeys($sourceId, [$columnKey]);

        $message = $updated > 0
            ? "Coluna removida. {$updated} registros atualizados."
            : 'Coluna removida. Nenhum registro atualizado.';

        return back()->with('status', $message);
    }

    public function bulkDelete(Request $request)
    {
        $this->authorizeAccess();
        $sourceId = session('explore_source_id');
        if (!$sourceId) {
            return back()->with('status', 'Selecione um arquivo no Explore.');
        }

        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            return back()->with('status', 'Seleção inválida.');
        }

        $ids = array_values(array_filter($ids, fn ($id) => is_numeric($id)));
        if (!$ids) {
            return back()->with('status', 'Nenhuma coluna selecionada.');
        }

        $settings = LeadColumnSetting::query()
            ->where('tenant_uuid', $this->tenantUuid())
            ->where('lead_source_id', $sourceId)
            ->whereIn('id', $ids)
            ->get(['id', 'column_key']);

        if ($settings->count() !== count($ids)) {
            return back()->with('status', 'Seleção inválida.');
        }

        $baseSelected = $settings->first(fn ($setting) => $this->isBaseKey($setting->column_key));
        if ($baseSelected) {
            return back()->with('status', 'Só colunas extras podem ser excluídas.');
        }

        $keys = $settings->pluck('column_key')->all();

        LeadColumnSetting::query()
            ->where('tenant_uuid', $this->tenantUuid())
            ->where('lead_source_id', $sourceId)
            ->whereIn('id', $ids)
            ->delete();

        $updated = $this->removeExtrasKeys($sourceId, $keys);

        $message = $updated > 0
            ? "Colunas removidas. {$updated} registros atualizados."
            : 'Colunas removidas. Nenhum registro atualizado.';

        return back()->with('status', $message);
    }

    public function merge(Request $request)
    {
        $this->authorizeAccess();
        $sourceId = session('explore_source_id');
        if (!$sourceId) {
            return back()->with('status', 'Selecione um arquivo no Explore.');
        }

        $ids = $request->input('ids', []);
        $targetId = $request->input('target_id');
        if (!is_array($ids) || !is_numeric($targetId)) {
            return back()->with('status', 'Seleção inválida.');
        }

        $ids = array_values(array_filter($ids, fn ($id) => is_numeric($id)));
        $targetId = (int) $targetId;
        if (count($ids) < 2) {
            return back()->with('status', 'Selecione ao menos duas colunas.');
        }
        if (!in_array($targetId, $ids, true)) {
            return back()->with('status', 'Coluna alvo inválida.');
        }

        $tenantUuid = $this->tenantUuid();
        $settings = LeadColumnSetting::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->whereIn('id', $ids)
            ->get(['id', 'column_key']);

        if ($settings->count() !== count($ids)) {
            return back()->with('status', 'Seleção inválida.');
        }

        $settingsById = $settings->keyBy('id');
        $orderedSettings = [];
        foreach ($ids as $id) {
            $setting = $settingsById->get((int) $id);
            if ($setting) $orderedSettings[] = $setting;
        }

        $baseAliases = $this->baseAliases();
        foreach ($orderedSettings as $setting) {
            $normalized = $this->normalizeKey($setting->column_key);
            if ($normalized === '' || isset($baseAliases[$normalized])) {
                return back()->with('status', 'Só colunas extras podem ser mescladas.');
            }
        }

        $targetSetting = $settingsById->get($targetId);
        if (!$targetSetting) {
            return back()->with('status', 'Coluna alvo inválida.');
        }

        $sourceKeys = array_values(array_unique(array_map(fn ($s) => $s->column_key, $orderedSettings)));
        $targetKey = $targetSetting->column_key;
        $sourceKeys = array_values(array_filter($sourceKeys, fn ($key) => $key !== $targetKey));
        array_unshift($sourceKeys, $targetKey);
        $deleteKeys = array_values(array_filter($sourceKeys, fn ($key) => $key !== $targetKey));

        $updated = 0;
        LeadNormalized::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->select(['id', 'extras_json'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($sourceKeys, $deleteKeys, $targetKey, &$updated) {
                foreach ($rows as $row) {
                    $original = $row->extras_json;
                    $extras = $original;
                    if (is_string($extras)) {
                        $decoded = json_decode($extras, true);
                        $extras = is_array($decoded) ? $decoded : [];
                    }
                    if (!is_array($extras)) $extras = [];

                    $targetVal = $extras[$targetKey] ?? null;
                    if ($this->isEmptyValue($targetVal)) {
                        foreach ($sourceKeys as $key) {
                            if ($key === $targetKey) continue;
                            $value = $extras[$key] ?? null;
                            if (!$this->isEmptyValue($value)) {
                                $targetVal = $value;
                                $extras[$targetKey] = $value;
                                break;
                            }
                        }
                    }

                    foreach ($deleteKeys as $key) {
                        unset($extras[$key]);
                    }

                    $extras = array_filter($extras, fn ($v) => $v !== null);
                    $newJson = $extras ? json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
                    $originalJson = is_string($original)
                        ? $original
                        : (is_array($original) ? json_encode($original, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null);

                    if ($newJson !== $originalJson) {
                        DB::table('leads_normalized')
                            ->where('id', $row->id)
                            ->update(['extras_json' => $newJson]);
                        $updated++;
                    }
                }
            });

        LeadColumnSetting::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->whereIn('id', $ids)
            ->where('id', '!=', $targetId)
            ->delete();

        if (Schema::hasTable('lead_overrides')) {
            $overrideRows = LeadOverride::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('lead_source_id', $sourceId)
                ->whereIn('column_key', $sourceKeys)
                ->get(['lead_id', 'column_key', 'value_text']);

            $byLead = $overrideRows->groupBy('lead_id');
            foreach ($byLead as $leadId => $rowsByLead) {
                $values = [];
                foreach ($rowsByLead as $item) {
                    $values[(string) $item->column_key] = $item->value_text;
                }

                $targetValue = $values[$targetKey] ?? null;
                if ($this->isEmptyValue($targetValue)) {
                    foreach ($sourceKeys as $key) {
                        if ($key === $targetKey) continue;
                        $candidate = $values[$key] ?? null;
                        if (!$this->isEmptyValue($candidate)) {
                            $targetValue = $candidate;
                            break;
                        }
                    }
                }

                if (!$this->isEmptyValue($targetValue)) {
                    LeadOverride::query()->updateOrCreate(
                        [
                            'tenant_uuid' => $tenantUuid,
                            'lead_source_id' => $sourceId,
                            'lead_id' => (int) $leadId,
                            'column_key' => $targetKey,
                        ],
                        [
                            'value_text' => is_scalar($targetValue)
                                ? (string) $targetValue
                                : json_encode($targetValue, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                            'updated_by' => auth()->id(),
                        ]
                    );
                }
            }

            if ($deleteKeys) {
                LeadOverride::query()
                    ->where('tenant_uuid', $tenantUuid)
                    ->where('lead_source_id', $sourceId)
                    ->whereIn('column_key', $deleteKeys)
                    ->delete();
            }
        }

        return back()->with('status', "Mescla concluída. {$updated} registros atualizados.");
    }

    private function ensureDefaults(int $sourceId): void
    {
        $tenantUuid = $this->tenantUuid();

        // Legacy compatibility: normalize old base key "lead" to "nome" once per source.
        $legacyLead = LeadColumnSetting::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->where('column_key', 'lead')
            ->first();
        $hasNome = LeadColumnSetting::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->where('column_key', 'nome')
            ->exists();
        if ($legacyLead && !$hasNome) {
            $legacyLead->update([
                'column_key' => 'nome',
                'label' => $legacyLead->label ?: 'Nome',
            ]);
        }

        $base = [
            ['key' => 'nome', 'label' => 'Nome', 'group' => 'Identidade', 'order' => 1],
            ['key' => 'cpf', 'label' => 'CPF', 'group' => 'Identidade', 'order' => 2],
            ['key' => 'email', 'label' => 'Email', 'group' => 'Contato', 'order' => 3],
            ['key' => 'phone', 'label' => 'Telefone', 'group' => 'Contato', 'order' => 4],
            ['key' => 'data_nascimento', 'label' => 'Data de nascimento', 'group' => 'Demografia', 'order' => 5],
            ['key' => 'sex', 'label' => 'Sexo', 'group' => 'Demografia', 'order' => 6],
        ];

        foreach ($base as $item) {
            LeadColumnSetting::firstOrCreate(
                ['tenant_uuid' => $tenantUuid, 'lead_source_id' => $sourceId, 'column_key' => $item['key']],
                [
                    'tenant_uuid' => $tenantUuid,
                    'lead_source_id' => $sourceId,
                    'label' => $item['label'],
                    'group_name' => $item['group'],
                    'visible' => true,
                    'sort_order' => $item['order'],
                ]
            );
        }
    }

    private function ensureExtras(int $sourceId): void
    {
        $tenantUuid = $this->tenantUuid();
        $aliases = $this->baseAliases();
        $existing = LeadColumnSetting::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->pluck('column_key')
            ->map(fn ($key) => $this->normalizeKey($key))
            ->values()
            ->all();

        $existingMap = array_fill_keys($existing, true);
        $extraKeys = [];

        $query = LeadNormalized::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->whereNotNull('extras_json')
            ->select('extras_json')
            ->orderByDesc('id')
            ->limit(2000);

        $query->cursor()->each(function ($row) use (&$extraKeys, $aliases, $existingMap) {
            $extras = $row->extras_json;
            if (is_string($extras)) {
                $decoded = json_decode($extras, true);
                $extras = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($extras)) {
                return;
            }
            foreach ($extras as $key => $value) {
                $normalized = $this->normalizeKey($key);
                if ($normalized === '' || isset($aliases[$normalized]) || isset($existingMap[$normalized])) {
                    continue;
                }
                $extraKeys[$normalized] = $key;
            }
        });

        if (Schema::hasTable('lead_overrides')) {
            LeadOverride::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('lead_source_id', $sourceId)
                ->select(['column_key'])
                ->distinct()
                ->get()
                ->each(function ($row) use (&$extraKeys, $aliases, $existingMap) {
                    $key = (string) ($row->column_key ?? '');
                    $normalized = $this->normalizeKey($key);
                    if ($normalized === '' || isset($aliases[$normalized]) || isset($existingMap[$normalized])) {
                        return;
                    }
                    $extraKeys[$normalized] = $key;
                });
        }

        if (!$extraKeys) {
            return;
        }

        $nextOrder = (int) LeadColumnSetting::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->max('sort_order');

        foreach ($extraKeys as $normalized => $originalKey) {
            $label = Str::title(str_replace(['_', '-'], ' ', $originalKey));
            LeadColumnSetting::firstOrCreate(
                ['tenant_uuid' => $tenantUuid, 'lead_source_id' => $sourceId, 'column_key' => $originalKey],
                [
                    'tenant_uuid' => $tenantUuid,
                    'lead_source_id' => $sourceId,
                    'label' => $label,
                    'group_name' => 'Extras',
                    'visible' => false,
                    'sort_order' => ++$nextOrder,
                ]
            );
        }
    }

    private function authorizeAccess(): void
    {
        if (!auth()->user()?->hasPermission('system.settings')) {
            abort(403);
        }
    }

    private function tenantUuid(): string
    {
        if (app()->bound('tenant_uuid')) {
            $uuid = trim((string) app('tenant_uuid'));
            if ($uuid !== '') {
                return $uuid;
            }
        }

        return \App\Support\TenantStorage::requireTenantUuid();
    }

    private function normalizeKey(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function baseAliases(): array
    {
        $aliases = [
            'lead','name','nome','fullname','fullnome',
            'email','e-mail','e_mail','mail',
            'cpf','documento','doc',
            'phone','telefone','tel','celular','phonee164',
            'data_nascimento','datanascimento','nascimento','dt_nascimento','data-de-nascimento',
            'sex','sexo','gender','genero',
        ];
        $normalized = [];
        foreach ($aliases as $alias) {
            $normalized[$this->normalizeKey($alias)] = true;
        }
        return $normalized;
    }

    private function isBaseKey(string $key): bool
    {
        $normalized = $this->normalizeKey($key);
        if ($normalized === '') return false;
        $aliases = $this->baseAliases();
        return isset($aliases[$normalized]);
    }

    private function removeExtrasKeys(int $sourceId, array $keys): int
    {
        $keys = array_values(array_filter(array_unique(array_map('strval', $keys)), fn ($k) => trim($k) !== ''));
        if (!$keys) return 0;

        $tenantUuid = $this->tenantUuid();
        $updated = 0;

        LeadNormalized::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->whereNotNull('extras_json')
            ->select(['id', 'extras_json'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($keys, &$updated) {
                foreach ($rows as $row) {
                    $original = $row->extras_json;
                    $extras = $original;
                    if (is_string($extras)) {
                        $decoded = json_decode($extras, true);
                        $extras = is_array($decoded) ? $decoded : [];
                    }
                    if (!is_array($extras)) $extras = [];

                    $changed = false;
                    foreach ($keys as $key) {
                        if (array_key_exists($key, $extras)) {
                            unset($extras[$key]);
                            $changed = true;
                        }
                    }

                    if (!$changed) {
                        continue;
                    }

                    $extras = array_filter($extras, fn ($v) => $v !== null);
                    $newJson = $extras ? json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
                    $originalJson = is_string($original)
                        ? $original
                        : (is_array($original) ? json_encode($original, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null);

                    if ($newJson !== $originalJson) {
                        DB::table('leads_normalized')
                            ->where('id', $row->id)
                            ->update(['extras_json' => $newJson]);
                        $updated++;
                    }
                }
            });

        if (Schema::hasTable('lead_overrides')) {
            LeadOverride::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('lead_source_id', $sourceId)
                ->whereIn('column_key', $keys)
                ->delete();
        }

        return $updated;
    }

    private function isEmptyValue($value): bool
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return count($value) === 0;
        if (is_object($value)) return count((array) $value) === 0;
        return false;
    }
}
