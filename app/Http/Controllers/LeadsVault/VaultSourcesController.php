<?php

namespace App\Http\Controllers\LeadsVault;

use App\Http\Controllers\Controller;
use App\Jobs\SecuritySyncMissingJob;
use App\Models\LeadSource;
use App\Services\GuestAuditService;
use App\Services\GuestIdentityService;

use App\Services\LeadsVault\LeadSourceUploadService;
use App\Services\LeadsVault\LeadSourceStatusService;
use App\Services\LeadsVault\LeadSourceCancelService;
use App\Services\LeadsVault\LeadSourceReprocessService;
use App\Services\LeadsVault\LeadSourcePurgeService;
use App\Services\LeadsVault\QueueHealthService;
use App\Support\TenantStorage;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VaultSourcesController extends Controller
{
    private function isGlobalSuperAdminContext(): bool
    {
        return auth()->check()
            && auth()->user()?->isSuperAdmin()
            && !session()->has('impersonate_user_id');
    }

    private function sourceQueryForActor()
    {
        return $this->isGlobalSuperAdminContext()
            ? LeadSource::withoutGlobalScopes()
            : LeadSource::query();
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

    private function ensureTenantForGlobalSuperBySourceIds(array $ids): ?string
    {
        if (!$this->isGlobalSuperAdminContext()) {
            return TenantStorage::tenantUuidOrNull();
        }

        $override = trim((string) session('tenant_uuid_override', ''));
        if ($override !== '') {
            app()->instance('tenant_uuid', $override);
            view()->share('tenant_uuid', $override);
            return $override;
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return null;
        }

        $tenantUuids = LeadSource::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->whereNotNull('tenant_uuid')
            ->pluck('tenant_uuid')
            ->unique()
            ->values();

        if ($tenantUuids->count() !== 1) {
            abort(422, 'Selecione arquivos do mesmo tenant para excluir.');
        }

        $tenantUuid = (string) $tenantUuids->first();
        if ($tenantUuid === '') {
            return null;
        }

        session(['tenant_uuid_override' => $tenantUuid]);
        app()->instance('tenant_uuid', $tenantUuid);
        view()->share('tenant_uuid', $tenantUuid);

        return $tenantUuid;
    }

    private function ensureTenantForGlobalSuperFromRequest(Request $request): ?string
    {
        if (!$this->isGlobalSuperAdminContext()) {
            return TenantStorage::tenantUuidOrNull();
        }

        $requested = trim((string) $request->input('tenant_uuid', ''));
        if ($requested !== '' && Str::isUuid($requested)) {
            $exists = \App\Models\Tenant::query()->where('uuid', $requested)->exists();
            if (!$exists) {
                abort(422, 'Tenant alvo inválido.');
            }
            session(['tenant_uuid_override' => $requested]);
            app()->instance('tenant_uuid', $requested);
            view()->share('tenant_uuid', $requested);
            return $requested;
        }

        $override = trim((string) session('tenant_uuid_override', ''));
        if ($override !== '') {
            app()->instance('tenant_uuid', $override);
            view()->share('tenant_uuid', $override);
            return $override;
        }

        return null;
    }

    private function ensureTenantForAnySuperAdminFromRequest(Request $request): ?string
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            return TenantStorage::tenantUuidOrNull();
        }

        $requested = trim((string) $request->input('tenant_uuid', ''));
        if ($requested !== '' && Str::isUuid($requested)) {
            $exists = \App\Models\Tenant::query()->where('uuid', $requested)->exists();
            if (!$exists) {
                abort(422, 'Tenant alvo inválido.');
            }
            session(['tenant_uuid_override' => $requested]);
            app()->instance('tenant_uuid', $requested);
            view()->share('tenant_uuid', $requested);
            return $requested;
        }

        if (app()->bound('tenant_uuid')) {
            $bound = trim((string) app('tenant_uuid'));
            if ($bound !== '') {
                return $bound;
            }
        }

        $override = trim((string) session('tenant_uuid_override', ''));
        if ($override !== '') {
            app()->instance('tenant_uuid', $override);
            view()->share('tenant_uuid', $override);
            return $override;
        }

        return TenantStorage::tenantUuidOrNull();
    }
    /* ======================================================
       VIEW
    ====================================================== */

    public function index(Request $request)
    {
        $this->authorize('viewAny', LeadSource::class);
        return redirect()->route('home');
    }


    /* ======================================================
       STORE (UPLOAD)
       HTTP only → Service
    ====================================================== */

    public function store(
        Request $request,
        LeadSourceUploadService $service,
        GuestAuditService $guestAudit
    ) {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('import', LeadSource::class);
            $tenantUuid = $this->ensureTenantForAnySuperAdminFromRequest($request);
            if ($request->user()?->isSuperAdmin() && !$tenantUuid) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Selecione um tenant alvo antes de importar.',
                ], 422);
            }
        }

        $request->validate([
            'files'   => ['required','array'],
            'files.*' => ['file','mimes:csv,xlsx,xls,txt']
        ]);

        $created = $service->handle(
            $request->file('files'),
            $this->extractMapping($request),
            auth()->id()
        );

        $createdIds = collect($created)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        if ($createdIds) {
            $sources = LeadSource::query()
                ->whereIn('id', $createdIds)
                ->get(['id', 'original_name', 'file_path', 'file_hash', 'file_size_bytes'])
                ->keyBy('id');
            foreach ($createdIds as $sourceId) {
                $source = $sources->get($sourceId);
                if (!$source) {
                    continue;
                }
                $guestAudit->logFileEvent($request, 'upload', [
                    'lead_source_id' => (int) $source->id,
                    'file_name' => (string) $source->original_name,
                    'file_path' => (string) $source->file_path,
                    'file_hash' => (string) $source->file_hash,
                    'file_size_bytes' => (int) $source->file_size_bytes,
                    'payload' => ['mode' => 'source_upload'],
                ]);
            }

            SecuritySyncMissingJob::dispatchAsync();
        }

        return response()->json([
            'ok'      => true,
            'sources' => $created
        ]);
    }


    /* ======================================================
       STATUS (POLLING)
    ====================================================== */

    public function status(LeadSourceStatusService $service)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('viewAny', LeadSource::class);
            if (request()->user()?->isSuperAdmin()) {
                $this->ensureTenantForAnySuperAdminFromRequest(request());
            }
        }

        $idsRaw = request()->query('ids', []);
        $ids = is_array($idsRaw)
            ? $idsRaw
            : array_filter(array_map('trim', explode(',', (string) $idsRaw)));
        $ids = array_values(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0));

        return response()->json([
            'sources' => $ids
                ? $service->byIds($ids)
                : $service->latest()
        ]);
    }

    public function health(Request $request, QueueHealthService $service)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('viewAny', LeadSource::class);
        }

        return response()->json($service->snapshot());
    }


    /* ======================================================
       CANCEL
    ====================================================== */

    public function cancel(
        int $id,
        LeadSourceCancelService $service
    ) {
        $source = $this->sourceQueryForActor()->findOrFail($id);
        $this->authorize('cancel', $source);

        $service->cancel($id);

        return response()->json([
            'ok' => true
        ]);
    }


    /* ======================================================
       REPROCESS
    ====================================================== */

    public function reprocess(
        int $id,
        LeadSourceReprocessService $service,
        Request $request,
        GuestAuditService $guestAudit
    ) {
        $source = $this->sourceQueryForActor()->findOrFail($id);
        $this->authorize('reprocess', $source);

        $rootSourceId = !empty($source->parent_source_id)
            ? (int) $source->parent_source_id
            : (int) $source->id;
        $rootSource = $this->sourceQueryForActor()->findOrFail($rootSourceId);

        $service->reprocess((int) $rootSource->id);
        $guestAudit->logFileEvent($request, 'reprocess', [
            'lead_source_id' => (int) $rootSource->id,
            'file_name' => (string) $rootSource->original_name,
            'file_path' => (string) $rootSource->file_path,
            'file_hash' => (string) $rootSource->file_hash,
            'file_size_bytes' => (int) $rootSource->file_size_bytes,
            'payload' => [
                'mode' => 'manual_reprocess',
                'requested_source_id' => (int) $source->id,
                'target_source_id' => (int) $rootSource->id,
            ],
        ]);

        return response()->json([
            'ok' => true
        ]);
    }


    /* ======================================================
       PURGE ALL (tenant)
    ====================================================== */

    public function purgeAll(Request $request, LeadSourcePurgeService $service, GuestAuditService $guestAudit)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('purge', LeadSource::class);
        }
        $tenantUuid = $this->ensureTenantForGlobalSuperBySourceIds([]);
        if (!$tenantUuid && $this->isGlobalSuperAdminContext()) {
            return response()->json([
                'ok' => false,
                'message' => 'Selecione um tenant alvo para excluir arquivos.',
            ], 422);
        }
        $tenantUuid = $tenantUuid ?: TenantStorage::tenantUuidOrNull();
        $auditSources = LeadSource::query()
            ->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->get(['id', 'original_name', 'file_path', 'file_hash', 'file_size_bytes']);

        $count = $service->purgeAll();
        foreach ($auditSources as $source) {
            $guestAudit->logFileEvent($request, 'delete', [
                'lead_source_id' => null,
                'file_name' => (string) $source->original_name,
                'file_path' => (string) $source->file_path,
                'file_hash' => (string) $source->file_hash,
                'file_size_bytes' => (int) $source->file_size_bytes,
                'payload' => [
                    'mode' => 'purge_all',
                    'deleted_lead_source_id' => (int) $source->id,
                ],
            ]);
        }

        return response()->json([
            'ok' => true,
            'deleted' => $count
        ]);
    }

    public function purgeSelected(Request $request, LeadSourcePurgeService $service, GuestAuditService $guestAudit)
    {
        if ($this->isGuest()) {
            $this->ensureGuestTenant();
        } else {
            $this->authorize('purge', LeadSource::class);
            if ($request->user()?->isSuperAdmin()) {
                $this->ensureTenantForAnySuperAdminFromRequest($request);
            }
        }
        $data = $request->validate([
            'ids'   => ['required','array'],
            'ids.*' => ['integer']
        ]);

        $tenantUuid = $this->ensureTenantForGlobalSuperBySourceIds($data['ids']);
        $tenantUuid = $tenantUuid ?: TenantStorage::tenantUuidOrNull();
        $auditSources = $this->sourceQueryForActor()
            ->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->whereIn('id', $data['ids'])
            ->get(['id', 'original_name', 'file_path', 'file_hash', 'file_size_bytes']);

        if ($auditSources->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'Nenhum arquivo selecionado foi encontrado no tenant atual.',
            ], 404);
        }

        $count = $service->purgeSelected($data['ids']);
        foreach ($auditSources as $source) {
            $guestAudit->logFileEvent($request, 'delete', [
                'lead_source_id' => null,
                'file_name' => (string) $source->original_name,
                'file_path' => (string) $source->file_path,
                'file_hash' => (string) $source->file_hash,
                'file_size_bytes' => (int) $source->file_size_bytes,
                'payload' => [
                    'mode' => 'purge_selected',
                    'deleted_lead_source_id' => (int) $source->id,
                ],
            ]);
        }

        return response()->json([
            'ok' => true,
            'deleted' => $count
        ]);
    }


    /* ======================================================
       MAPPING (helper leve — não é regra de negócio)
    ====================================================== */

    private function extractMapping(Request $r): array
    {
        return array_filter([
            'doc'        => $r->input('map_doc'),
            'cpf'        => $r->input('map_cpf'),
            'email'      => $r->input('map_email'),
            'phone'      => $r->input('map_phone'),
            'name'       => $r->input('map_name'),
            'birth_date' => $r->input('map_birth_date'),
        ]);
    }
}
