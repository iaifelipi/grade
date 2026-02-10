<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\LeadSource;
use Illuminate\Http\Request;

class UserAdminController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isGlobalSuper = $user->isSuperAdmin() && !session()->has('impersonate_user_id');
        $tenantUuid = app()->bound('tenant_uuid') ? app('tenant_uuid') : $user->tenant_uuid;
        $currentSourceId = (int) session('explore_source_id', 0);

        $users = User::query()
            ->when(!$isGlobalSuper && $tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->with(['roles', 'conta'])
            ->orderBy('id')
            ->get();

        $roles = Role::query()
            ->when(!$isGlobalSuper && $tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->orderBy('name')
            ->get();

        $sourcesQuery = $isGlobalSuper
            ? LeadSource::withoutGlobalScopes()
            : LeadSource::query()->where('tenant_uuid', $tenantUuid);
        $topbarSources = $sourcesQuery
            ->orderByDesc('id')
            ->get(['id', 'original_name']);

        return view('admin.users.index', compact('users', 'roles', 'isGlobalSuper', 'topbarSources', 'currentSourceId'));
    }

    public function updateRoles(Request $request, int $id)
    {
        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string'],
        ]);

        $tenantUuid = app()->bound('tenant_uuid') ? app('tenant_uuid') : null;

        $auth = auth()->user();
        $requestedRoles = collect($data['roles'] ?? [])->unique()->values();
        if ($requestedRoles->contains('admin') && !$auth->isSuperAdmin()) {
            abort(403, 'Apenas super admin pode atribuir a role admin.');
        }

        $user = User::query()->findOrFail($id);
        $resolvedTenant = $tenantUuid ?: $user->tenant_uuid;
        if (!$resolvedTenant) {
            abort(403, 'Selecione uma conta para atualizar perfis.');
        }
        if ($tenantUuid && $user->tenant_uuid !== $tenantUuid && !$auth->isSuperAdmin()) {
            abort(403, 'Usuário fora da conta atual.');
        }

        $roleIds = Role::query()
            ->where('tenant_uuid', $resolvedTenant)
            ->whereIn('name', $requestedRoles)
            ->pluck('id')
            ->all();

        $user->roles()->sync($roleIds);

        return back()->with('status', 'Permissões atualizadas.');
    }

    public function impersonate(int $id)
    {
        $auth = auth()->user();
        if (!$auth->isSuperAdmin()) {
            abort(403, 'Acesso negado.');
        }

        $user = User::findOrFail($id);
        session(['impersonate_user_id' => $user->id]);

        return back()->with('status', "Impersonação ativa: {$user->name}.");
    }

    public function stopImpersonate()
    {
        session()->forget('impersonate_user_id');

        return back()->with('status', 'Impersonação encerrada.');
    }

    public function promoteToAdmin(Request $request, int $id)
    {
        $auth = auth()->user();
        if (!$auth->isSuperAdmin()) {
            abort(403, 'Apenas super admin pode promover.');
        }

        $data = $request->validate([
            'plan' => ['required', 'in:free,starter,pro'],
        ]);

        $user = User::findOrFail($id);
        $tenant = Tenant::query()
            ->where('uuid', $user->tenant_uuid)
            ->first();

        if ($tenant) {
            $tenant->plan = $data['plan'];
            $tenant->save();
        }

        $adminRole = $this->ensureTenantRoles($user->tenant_uuid);
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        return back()->with('status', 'Usuário promovido a admin.');
    }

    private function ensureTenantRoles(string $tenantUuid): Role
    {
        $permissions = [
            'leads.import',
            'leads.view',
            'leads.export',
            'leads.delete',
            'leads.normalize',
            'leads.merge',
            'leads.clean',
            'analytics.view',
            'analytics.export',
            'automation.run',
            'automation.cancel',
            'automation.reprocess',
            'users.manage',
            'roles.manage',
            'system.settings',
            'audit.view_sensitive',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_uuid' => $tenantUuid,
        ]);

        Role::firstOrCreate([
            'name' => 'operator',
            'guard_name' => 'web',
            'tenant_uuid' => $tenantUuid,
        ]);

        Role::firstOrCreate([
            'name' => 'viewer',
            'guard_name' => 'web',
            'tenant_uuid' => $tenantUuid,
        ]);

        $admin->syncPermissionsByName(Permission::query()->pluck('name')->all());

        return $admin;
    }

}
