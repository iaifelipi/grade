<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\LeadSource;

class RoleAdminController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isGlobalSuper = $user->isSuperAdmin() && !session()->has('impersonate_user_id');
        $tenantUuid = app()->bound('tenant_uuid') ? app('tenant_uuid') : $user->tenant_uuid;
        $currentSourceId = (int) session('explore_source_id', 0);

        $roles = Role::query()
            ->when(!$isGlobalSuper && $tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->with('permissions')
            ->orderBy('name')
            ->get();

        $permissions = Permission::query()
            ->orderBy('name')
            ->get();

        $tenantSlugs = Tenant::query()
            ->whereIn('uuid', $roles->pluck('tenant_uuid')->filter()->unique()->all())
            ->pluck('slug', 'uuid');

        $sourcesQuery = $isGlobalSuper
            ? LeadSource::withoutGlobalScopes()
            : LeadSource::query()->where('tenant_uuid', $tenantUuid);
        $topbarSources = $sourcesQuery
            ->orderByDesc('id')
            ->get(['id', 'original_name']);

        return view('admin.roles.index', compact('roles', 'permissions', 'isGlobalSuper', 'tenantSlugs', 'topbarSources', 'currentSourceId'));
    }

    public function updatePermissions(Request $request, int $roleId)
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $tenantUuid = app()->bound('tenant_uuid') ? app('tenant_uuid') : null;
        if (!$tenantUuid) {
            abort(403, 'Selecione uma conta para atualizar permissões.');
        }

        $role = Role::query()
            ->where('tenant_uuid', $tenantUuid)
            ->findOrFail($roleId);

        $perms = collect($data['permissions'] ?? [])->unique()->values()->all();
        $role->syncPermissionsByName($perms);

        return back()->with('status', 'Permissões do perfil atualizadas.');
    }
}
