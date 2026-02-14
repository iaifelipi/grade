<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTenantUserGroupPermissionsRequest;
use App\Models\Tenant;
use App\Models\TenantUserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantUserGroupAdminController extends Controller
{
    /**
     * @return array<string,array{label:string,module:string}>
     */
    private function permissionCatalog(): array
    {
        return [
            '*' => ['label' => 'Full access (all modules)', 'module' => 'global'],
            'imports.manage' => ['label' => 'Import files and reprocess', 'module' => 'imports'],
            'data.edit' => ['label' => 'Edit columns and data quality', 'module' => 'data'],
            'campaigns.manage' => ['label' => 'Manage campaign settings/templates', 'module' => 'campaigns'],
            'campaigns.run' => ['label' => 'Run campaign dispatches', 'module' => 'campaigns'],
            'inbox.manage' => ['label' => 'Manage inbox channels/settings', 'module' => 'inbox'],
            'inbox.view' => ['label' => 'Read inbox conversations', 'module' => 'inbox'],
            'exports.manage' => ['label' => 'Manage export presets', 'module' => 'exports'],
            'exports.run' => ['label' => 'Run exports', 'module' => 'exports'],
            'exports.view' => ['label' => 'View exported files/history', 'module' => 'exports'],
        ];
    }

    public function index(Request $request): View
    {
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $selectedTenantUuid = trim((string) $request->query('tenant_uuid', ''));

        $tenant = null;
        $groupsQuery = TenantUserGroup::query()
            ->with('tenant:id,uuid,slug,name')
            ->orderByDesc('is_default')
            ->orderBy('name');

        $tenants = collect();

        if ($isGlobalSuper) {
            $tenants = Tenant::query()
                ->orderBy('name')
                ->get(['id', 'uuid', 'slug', 'name', 'plan']);

            if ($selectedTenantUuid !== '') {
                $tenant = Tenant::query()->where('uuid', $selectedTenantUuid)->first();
                if ($tenant) {
                    $groupsQuery->where('tenant_id', $tenant->id);
                }
            }
        } else {
            $tenant = $this->resolveTenantFromContext();
            $groupsQuery->where('tenant_id', $tenant->id);
            $tenants = Tenant::query()->where('id', $tenant->id)->get(['id', 'uuid', 'slug', 'name', 'plan']);
        }

        $groups = $groupsQuery->get();

        return view('admin.tenant-users.groups', [
            'tenant' => $tenant,
            'groups' => $groups,
            'permissionCatalog' => $this->permissionCatalog(),
            'isGlobalSuper' => $isGlobalSuper,
            'tenants' => $tenants,
            'selectedTenantUuid' => $selectedTenantUuid,
        ]);
    }

    public function updatePermissions(UpdateTenantUserGroupPermissionsRequest $request, int $id): RedirectResponse
    {
        $groupQuery = TenantUserGroup::query();

        if (!$this->isGlobalSuperAdminContext()) {
            $tenant = $this->resolveTenantFromContext();
            $groupQuery->where('tenant_id', $tenant->id);
        }

        $group = $groupQuery->findOrFail($id);

        $allowed = array_keys($this->permissionCatalog());
        $permissions = collect($request->validated('permissions', []))
            ->map(fn ($p) => (string) $p)
            ->filter(fn ($p) => in_array($p, $allowed, true))
            ->unique()
            ->values();

        // If wildcard is selected, keep only wildcard to simplify checks and UX.
        if ($permissions->contains('*')) {
            $permissions = collect(['*']);
        }

        $group->permissions_json = $permissions->all();
        $group->save();

        return back()->with('status', 'Group permissions updated.');
    }

    private function isGlobalSuperAdminContext(): bool
    {
        return auth()->check()
            && auth()->user()?->isSuperAdmin()
            && !session()->has('impersonate_user_id');
    }

    private function resolveTenantFromContext(): Tenant
    {
        $tenantUuid = app()->bound('tenant_uuid') ? (string) app('tenant_uuid') : '';
        if ($tenantUuid === '') {
            abort(403, 'Tenant context not selected.');
        }

        return Tenant::query()->where('uuid', $tenantUuid)->firstOrFail();
    }
}
