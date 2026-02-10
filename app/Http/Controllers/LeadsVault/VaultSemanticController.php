<?php

namespace App\Http\Controllers\LeadsVault;

use App\Http\Controllers\Controller;
use App\Models\LeadSource;
use App\Models\LeadSourceSemantic;
use App\Models\SemanticLocation;
use App\Services\LeadSourceSemanticSuggestionService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VaultSemanticController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | TENANT HELPER (PIXIP CANÔNICO)
    |--------------------------------------------------------------------------
    */

    private function isGlobalSuperAdminContext(): bool
    {
        return auth()->check()
            && auth()->user()?->isSuperAdmin()
            && !session()->has('impersonate_user_id');
    }

    private function tenant(?int $sourceId = null): string
    {
        if ($sourceId && $sourceId > 0 && $this->isGlobalSuperAdminContext()) {
            $source = LeadSource::withoutGlobalScopes()
                ->whereKey($sourceId)
                ->first(['tenant_uuid']);
            if ($source && filled($source->tenant_uuid)) {
                $tenantUuid = (string) $source->tenant_uuid;
                session(['tenant_uuid_override' => $tenantUuid]);
                app()->instance('tenant_uuid', $tenantUuid);
                view()->share('tenant_uuid', $tenantUuid);
                return $tenantUuid;
            }
        }

        return \App\Support\TenantStorage::requireTenantUuid();
    }

    private function sourceOrFail(int $id): LeadSource
    {
        if ($this->isGlobalSuperAdminContext()) {
            return LeadSource::withoutGlobalScopes()->where('id', $id)->firstOrFail();
        }

        return LeadSource::where('tenant_uuid', $this->tenant($id))->where('id', $id)->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index(int $id)
    {
        $source = $this->sourceOrFail($id);
        $this->authorize('normalize', $source);

        return view('vault.semantic.index', compact('source'));
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW (LOAD IDENTIDADE)
    |--------------------------------------------------------------------------
    */

    public function show(int $id)
    {
        $source = $this->sourceOrFail($id);
        $this->authorize('normalize', $source);
        $tenantUuid = (string) $source->tenant_uuid;

        $semantic = LeadSourceSemantic::with('locations')
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'locations' => $semantic?->locations ?? [],
                'segment'   => $this->pair('semantic_segments', $semantic?->segment_id),
                'niche'     => $this->pair('semantic_niches',   $semantic?->niche_id),
                'origin'    => $this->pair('semantic_origins',  $semantic?->origin_id),
                'anchor'    => $source->semantic_anchor ?? 'Brasil',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUGGEST (AI)
    |--------------------------------------------------------------------------
    */

    public function suggest(
        LeadSourceSemanticSuggestionService $service,
        int $id
    ) {
        $source = $this->sourceOrFail($id);
        $this->authorize('normalize', $source);

        return response()->json([
            'success' => true,
            'data'    => $service->suggest($source),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE (🔥 MULTI-TENANT SAFE)
    |--------------------------------------------------------------------------
    */

    public function save(Request $request, int $id)
    {
        $source = $this->sourceOrFail($id);
        $this->authorize('normalize', $source);
        $tenantUuid = (string) $source->tenant_uuid;

        DB::transaction(function () use ($request, $source, $tenantUuid) {

            $semantic = LeadSourceSemantic::updateOrCreate(
                [
                    'tenant_uuid'    => $tenantUuid,
                    'lead_source_id'=> $source->id,
                ],
                [
                    'segment_id' => collect($request->segment_ids)->first(),
                    'niche_id'   => collect($request->niche_ids)->first(),
                    'origin_id'  => collect($request->origin_ids)->first(),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | LOCATIONS (limpa e recria — simples e rápido)
            |--------------------------------------------------------------------------
            */

            SemanticLocation::where('tenant_uuid', $tenantUuid)
                ->where('lead_source_semantic_id', $semantic->id)
                ->delete();

            foreach ($request->locations ?? [] as $loc) {
                SemanticLocation::create([
                    'tenant_uuid' => $tenantUuid,
                    'lead_source_semantic_id' => $semantic->id,
                    'type'   => $loc['type'], // city/state/country
                    'ref_id' => $loc['ref_id'],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ANCHOR
            |--------------------------------------------------------------------------
            */

            $anchor = trim($request->anchor ?? '') ?: 'Brasil';

            $source->update([
                'semantic_anchor' => $anchor
            ]);
        });

        return response()->json(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | AUTOCOMPLETE UNIFICADO (🔥 AGORA COM LOCATION)
    |--------------------------------------------------------------------------
    */

    public function autocompleteUnified(Request $request)
    {
        $q = trim($request->query('q'));
        abort_if(strlen($q) < 2, 422);

        $like = "%{$q}%";

        $items = DB::select("

            /* SEGMENT */
            SELECT 'segment' taxonomy, id, name label, NULL type, NULL ref_id
            FROM semantic_segments
            WHERE name LIKE ?

            UNION ALL

            /* NICHE */
            SELECT 'niche', id, name, NULL, NULL
            FROM semantic_niches
            WHERE name LIKE ?

            UNION ALL

            /* ORIGIN */
            SELECT 'origin', id, name, NULL, NULL
            FROM semantic_origins
            WHERE name LIKE ?

            UNION ALL

            /* CITY */
            SELECT 'location', id, name, 'city', id
            FROM semantic_cities
            WHERE name LIKE ?

            UNION ALL

            /* STATE */
            SELECT 'location', id, name, 'state', id
            FROM semantic_states
            WHERE name LIKE ?

            UNION ALL

            /* COUNTRY */
            SELECT 'location', id, name, 'country', id
            FROM semantic_countries
            WHERE name LIKE ?

            LIMIT 30
        ", [$like,$like,$like,$like,$like,$like]);

        return response()->json([
            'success' => true,
            'items'   => $items,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    protected function pair(string $table, ?int $id): array
    {
        if (!$id) return [];

        $row = DB::table($table)->where('id', $id)->first();

        return $row ? [[
            'id'   => $row->id,
            'name' => $row->name
        ]] : [];
    }
}
