<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SemanticTaxonomyController extends Controller
{
    public function index()
    {
        $this->authorizeAccess();
        $authUser = auth()->user();
        $isGlobalSuper = $authUser && $authUser->isSuperAdmin() && !session()->has('impersonate_user_id');
        $tenantUuid = app()->bound('tenant_uuid') ? (string) app('tenant_uuid') : (string) ($authUser->tenant_uuid ?? '');
        $currentSourceId = (int) session('explore_source_id', 0);

        $sourcesQuery = $isGlobalSuper
            ? LeadSource::withoutGlobalScopes()
            : LeadSource::query()->where('tenant_uuid', $tenantUuid);
        $topbarSources = $sourcesQuery
            ->orderByDesc('id')
            ->get(['id', 'original_name']);

        return view('admin.semantic.index', [
            'segments' => $this->fetchTable('segments'),
            'niches' => $this->fetchTable('niches'),
            'origins' => $this->fetchTable('origins'),
            'topbarSources' => $topbarSources,
            'currentSourceId' => $currentSourceId,
        ]);
    }

    public function store(Request $request, string $type)
    {
        $this->authorizeAccess();
        $table = $this->resolveTable($type);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return back()->with('status', 'Nome inválido.');
        }

        $exists = DB::table($table)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();
        if ($exists) {
            return back()->with('status', 'Já existe um item com esse nome.');
        }

        $payload = ['name' => $name, 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn($table, 'normalized')) {
            $payload['normalized'] = Str::slug($name, ' ');
        }

        DB::table($table)->insert($payload);

        return back()->with('status', 'Item criado.');
    }

    public function update(Request $request, string $type, int $id)
    {
        $this->authorizeAccess();
        $table = $this->resolveTable($type);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return back()->with('status', 'Nome inválido.');
        }

        $exists = DB::table($table)
            ->where('id', '!=', $id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return back()->with('status', 'Já existe um item com esse nome.');
        }

        $payload = ['name' => $name, 'updated_at' => now()];
        if (Schema::hasColumn($table, 'normalized')) {
            $payload['normalized'] = Str::slug($name, ' ');
        }

        DB::table($table)->where('id', $id)->update($payload);

        return back()->with('status', 'Item atualizado.');
    }

    public function destroy(string $type, int $id)
    {
        $this->authorizeAccess();
        $table = $this->resolveTable($type);

        $this->cleanupUsage($table, [$id]);

        DB::table($table)->where('id', $id)->delete();

        return back()->with('status', 'Item removido.');
    }

    public function bulkAdd(Request $request, string $type)
    {
        $this->authorizeAccess();
        $table = $this->resolveTable($type);

        $data = $request->validate([
            'items' => ['required', 'string'],
        ]);

        $lines = preg_split('/\r\n|\r|\n/', $data['items']);
        $names = collect($lines)
            ->map(fn($v) => trim($v))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return back()->with('status', 'Nenhum item válido para adicionar.');
        }

        $existing = DB::table($table)
            ->whereIn(DB::raw('LOWER(name)'), $names->map(fn($n) => mb_strtolower($n))->all())
            ->pluck('name')
            ->all();

        $existingLower = array_map('mb_strtolower', $existing);

        $inserted = 0;
        foreach ($names as $name) {
            if (in_array(mb_strtolower($name), $existingLower, true)) {
                continue;
            }
            $payload = ['name' => $name, 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn($table, 'normalized')) {
                $payload['normalized'] = Str::slug($name, ' ');
            }
            DB::table($table)->insert($payload);
            $inserted++;
        }

        return back()->with('status', "Itens adicionados: {$inserted}.");
    }

    public function bulkDelete(Request $request, string $type)
    {
        $this->authorizeAccess();
        $table = $this->resolveTable($type);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique($data['ids']));
        if (!$ids) {
            return back()->with('status', 'Nenhum item selecionado.');
        }

        $this->cleanupUsage($table, $ids);

        DB::table($table)->whereIn('id', $ids)->delete();

        return back()->with('status', 'Itens removidos.');
    }


    private function fetchTable(string $type)
    {
        $table = $this->resolveTable($type);

        return DB::table($table)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function resolveTable(string $type): string
    {
        return match ($type) {
            'segments' => 'semantic_segments',
            'niches' => 'semantic_niches',
            'origins' => 'semantic_origins',
            default => abort(404),
        };
    }

    private function cleanupUsage(string $table, array $ids): void
    {
        $ids = array_values(array_filter($ids, fn($id) => is_numeric($id)));
        if (!$ids) return;

        $column = match ($table) {
            'semantic_segments' => 'segment_id',
            'semantic_niches' => 'niche_id',
            'semantic_origins' => 'origin_id',
            default => null,
        };

        if (!$column) return;

        DB::table('lead_source_semantics')
            ->whereIn($column, $ids)
            ->update([$column => null]);

        DB::table('leads_normalized')
            ->whereIn($column, $ids)
            ->update([$column => null]);
    }

    private function authorizeAccess(): void
    {
        if (!auth()->user()?->hasPermission('system.settings')) {
            abort(403);
        }
    }
}
