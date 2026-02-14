<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Tenant;
use App\Services\Admin\AdminAuditService;
use App\Services\Users\UserTenantSyncService;
use App\Services\Users\UsernameService;
use App\Services\Tenants\TenantDeletionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserAdminController extends Controller
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

    public function index(Request $request)
    {
        $user = auth()->user();
        $isGlobalSuper = $this->isGlobalSuperAdminContext();

        // Superadmin always sees global system users (no tenant override required).
        $tenantUuid = $isGlobalSuper
            ? null
            : (app()->bound('tenant_uuid') ? app('tenant_uuid') : $user->tenant_uuid);

        $users = User::query()
            ->when(!$isGlobalSuper && $tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->with(['roles', 'conta'])
            ->orderBy('id')
            ->get();

        $roles = Role::query()
            ->when(!$isGlobalSuper && $tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->orderBy('name')
            ->get();

        // Used by the Users admin screen to avoid showing roles that don't exist for a given tenant.
        // (Global super admins can see multiple tenants at once.)
        $roleNamesByTenant = $roles
            ->groupBy(fn (Role $role) => (string) ($role->tenant_uuid ?? ''))
            ->map(fn ($group) => $group->pluck('name')->filter()->unique()->values())
            ->all();

        $tenants = collect();
        if ($isGlobalSuper) {
            $tenants = Tenant::query()
                ->orderBy('name')
                ->get(['uuid', 'slug', 'name', 'plan']);
        } elseif ($tenantUuid) {
            $tenants = Tenant::query()
                ->where('uuid', $tenantUuid)
                ->get(['uuid', 'slug', 'name', 'plan']);
        }

        return view('admin.users.index', compact('users', 'roles', 'isGlobalSuper', 'tenants', 'roleNamesByTenant'));
    }

    public function store(Request $request)
    {
        $auth = auth()->user();
        $tenantUuid = app()->bound('tenant_uuid') ? app('tenant_uuid') : $auth->tenant_uuid;

        if ($request->has('username')) {
            $rawUsername = trim((string) $request->input('username', ''));
            $request->merge([
                'username' => $rawUsername !== '' ? $rawUsername : null,
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'confirmed', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'max:190', 'confirmed'],
            'locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::in(['active', 'disabled'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string'],
            'email_verified' => ['nullable', 'boolean'],
        ]);

        $resolvedTenant = (string) ($tenantUuid ?: '');
        if ($resolvedTenant === '') {
            abort(403, 'Conta do usuário administrador não definida.');
        }

        $requestedRoles = collect($data['roles'] ?? [])->unique()->values();
        if ($requestedRoles->isEmpty()) {
            $requestedRoles = collect(['viewer']);
        }
        if ($requestedRoles->contains('admin') && !$auth->isSuperAdmin()) {
            abort(403, 'Apenas super admin pode atribuir a role admin.');
        }

        // Ensure baseline roles exist for this tenant; otherwise the UI may show roles that can't be assigned yet.
        $this->ensureTenantRoles($resolvedTenant);

        [$user, $tenantUser] = DB::transaction(function () use ($data, $resolvedTenant, $requestedRoles) {
            $user = new User();
            $user->name = $data['name'];
            $user->email = $data['email'];
            $usernameSvc = app(UsernameService::class);
            $requestedUsername = isset($data['username']) ? trim((string) $data['username']) : '';
            if ($requestedUsername !== '') {
                $normalized = $usernameSvc->normalize($requestedUsername);
                $user->username = $normalized !== '' ? $normalized : $usernameSvc->generateUniqueFromEmail($user->email);
            } else {
                $user->username = $usernameSvc->generateUniqueFromEmail($user->email);
            }
            $user->tenant_uuid = $resolvedTenant;
            $user->is_super_admin = false;
            $user->locale = trim((string) ($data['locale'] ?? '')) !== '' ? trim((string) $data['locale']) : 'en';
            $user->timezone = trim((string) ($data['timezone'] ?? '')) !== '' ? trim((string) $data['timezone']) : 'America/Sao_Paulo';
            $user->password = Hash::make((string) $data['password']);
            $user->email_verified_at = !empty($data['email_verified']) ? now() : null;
            $user->disabled_at = ($data['status'] ?? 'active') === 'disabled' ? now() : null;
            $user->save();

            $roleIds = Role::query()
                ->where('tenant_uuid', $resolvedTenant)
                ->whereIn('name', $requestedRoles)
                ->pluck('id')
                ->all();
            $user->roles()->sync($roleIds);

            $tenantUser = app(UserTenantSyncService::class)->syncFromUser($user);

            return [$user, $tenantUser];
        });

        app(AdminAuditService::class)->log('admin.user.created', $user->id, [
            'username' => $user->username,
            'tenant_uuid' => $user->tenant_uuid,
            'roles' => $requestedRoles->all(),
            'tenant_user_id' => $tenantUser->id ?? null,
        ]);

        return back()->with('status', 'Usuário criado.');
    }

    public function update(Request $request, int $id)
    {
        $auth = auth()->user();
        $tenantUuid = app()->bound('tenant_uuid') ? app('tenant_uuid') : null;

        $user = User::query()->findOrFail($id);
        if ($tenantUuid && $user->tenant_uuid !== $tenantUuid && !$auth->isSuperAdmin()) {
            abort(403, 'Usuário fora da conta atual.');
        }

        if ($request->has('username')) {
            $rawUsername = trim((string) $request->input('username', ''));
            $request->merge([
                'username' => $rawUsername !== '' ? $rawUsername : null,
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:190', 'confirmed'],
            'locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::in(['active', 'disabled'])],
            'email_verified' => ['nullable', 'boolean'],
        ]);

        // Safety rule: superadmin accounts cannot be disabled through profile updates.
        if ($user->isSuperAdmin() && ($data['status'] ?? 'active') === 'disabled') {
            abort(403, 'Não é permitido desativar super admin.');
        }

        DB::transaction(function () use ($data, $user) {
            $user->name = $data['name'];
            $user->email = $data['email'];
            if (array_key_exists('username', $data)) {
                $u = trim((string) ($data['username'] ?? ''));
                if ($u === '') {
                    $user->username = null;
                } else {
                    $normalized = app(UsernameService::class)->normalize($u);
                    $user->username = $normalized !== '' ? $normalized : null;
                }
            }
            $user->email_verified_at = !empty($data['email_verified']) ? ($user->email_verified_at ?: now()) : null;
            $user->locale = trim((string) ($data['locale'] ?? '')) !== '' ? trim((string) $data['locale']) : $user->locale;
            $user->timezone = trim((string) ($data['timezone'] ?? '')) !== '' ? trim((string) $data['timezone']) : $user->timezone;
            $user->disabled_at = ($data['status'] ?? 'active') === 'disabled' ? now() : null;
            if (!empty($data['password'])) {
                $user->password = Hash::make((string) $data['password']);
            }
            $user->save();

            app(UserTenantSyncService::class)->syncFromUser($user);
        });

        app(AdminAuditService::class)->log('admin.user.updated', $user->id, [
            'username' => $user->username,
            'email_verified' => !empty($data['email_verified']),
            'password_changed' => !empty($data['password']),
        ]);

        return back()->with('status', 'Usuário atualizado.');
    }

    public function disable(Request $request, int $id)
    {
        $auth = auth()->user();
        if (!$auth->hasPermission('users.manage')) {
            abort(403, 'Acesso negado.');
        }

        $tenantUuid = app()->bound('tenant_uuid') ? app('tenant_uuid') : null;
        $user = User::query()->findOrFail($id);
        if ($user->isSuperAdmin()) {
            abort(403, 'Não é permitido desativar super admin.');
        }
        if ($auth->id === $user->id) {
            abort(403, 'Não é permitido desativar sua própria conta.');
        }
        if ($tenantUuid && $user->tenant_uuid !== $tenantUuid && !$auth->isSuperAdmin()) {
            abort(403, 'Usuário fora da conta atual.');
        }

        DB::transaction(function () use ($user) {
            $user->disabled_at = now();
            $user->save();
            app(UserTenantSyncService::class)->syncFromUser($user);
        });

        app(AdminAuditService::class)->log('admin.user.disabled', $user->id, [
            'username' => $user->username,
        ]);

        return back()->with('status', 'Usuário desativado.');
    }

    public function destroy(Request $request, int $id)
    {
        $auth = auth()->user();
        if (!$auth->hasPermission('users.manage')) {
            abort(403, 'Acesso negado.');
        }

        $tenantUuid = app()->bound('tenant_uuid') ? app('tenant_uuid') : null;
        $user = User::query()->findOrFail($id);
        $userTenantUuid = trim((string) ($user->tenant_uuid ?? ''));

        if ($user->isSuperAdmin()) {
            abort(403, 'Não é permitido excluir super admin.');
        }
        if ($auth->id === $user->id) {
            abort(403, 'Não é permitido excluir sua própria conta.');
        }
        if ($tenantUuid && $user->tenant_uuid !== $tenantUuid && !$auth->isSuperAdmin()) {
            abort(403, 'Usuário fora da conta atual.');
        }

        DB::transaction(function () use ($user) {
            // Defensive cleanup; user_role is cascadeOnDelete, but other tables may not be.
            try {
                $user->roles()->detach();
            } catch (\Throwable $e) {
                // ignore
            }

            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $user->id)
                    ->delete();
            }

            app(UserTenantSyncService::class)->deleteSyncedTenantUser($user);

            $user->delete();
        });

        app(AdminAuditService::class)->log('admin.user.deleted', $user->id, [
            'username' => $user->username,
            'tenant_uuid' => $user->tenant_uuid,
        ]);

        // If it was the last user in this tenant, delete the tenant and all its data as well.
        if ($userTenantUuid !== '') {
            $remaining = (int) User::query()->where('tenant_uuid', $userTenantUuid)->count();
            if ($remaining === 0) {
                $wantsTenantDelete = $request->boolean('delete_tenant');
                $ack = $request->boolean('confirm_delete_tenant');
                $confirmText = trim((string) $request->input('confirm_delete_tenant_text', ''));

                $tenant = Tenant::query()->where('uuid', $userTenantUuid)->first();
                $tenantSlug = $tenant ? trim((string) ($tenant->slug ?? '')) : '';

                $confirmOk = $ack
                    && $confirmText !== ''
                    && ($confirmText === $userTenantUuid || ($tenantSlug !== '' && $confirmText === $tenantSlug));

                if ($wantsTenantDelete && $confirmOk) {
                    $result = app(TenantDeletionService::class)->deleteTenantAndAllData($userTenantUuid);

                    // Clear local session override if it points to the deleted tenant.
                    if ((string) session('tenant_uuid_override', '') === $userTenantUuid) {
                        session()->forget('tenant_uuid_override');
                    }

                    app(AdminAuditService::class)->log('admin.tenant.deleted_after_last_user', null, [
                        'tenant_uuid' => $userTenantUuid,
                        'result' => $result,
                    ]);

                    return back()->with('status', 'Usuário excluído. Conta removida (confirmado: último usuário).');
                }

                return back()->with('status', 'Usuário excluído. Conta ficou sem usuários (tenant órfão). Para remover a conta e todos os dados, use a confirmação forte.');
            }
        }

        return back()->with('status', 'Usuário excluído.');
    }

    public function enable(Request $request, int $id)
    {
        $auth = auth()->user();
        if (!$auth->hasPermission('users.manage')) {
            abort(403, 'Acesso negado.');
        }

        $tenantUuid = app()->bound('tenant_uuid') ? app('tenant_uuid') : null;
        $user = User::query()->findOrFail($id);
        if ($tenantUuid && $user->tenant_uuid !== $tenantUuid && !$auth->isSuperAdmin()) {
            abort(403, 'Usuário fora da conta atual.');
        }

        DB::transaction(function () use ($user) {
            $user->disabled_at = null;
            $user->save();
            app(UserTenantSyncService::class)->syncFromUser($user);
        });

        app(AdminAuditService::class)->log('admin.user.enabled', $user->id, [
            'username' => $user->username,
        ]);

        return back()->with('status', 'Usuário reativado.');
    }

    public function updateRoles(Request $request, int $id)
    {
        $auth = auth()->user();
        if (!$auth->hasPermission('users.manage')) {
            abort(403, 'Acesso negado.');
        }

        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string'],
        ]);

        $tenantUuid = app()->bound('tenant_uuid') ? app('tenant_uuid') : null;

        $requestedRoles = collect($data['roles'] ?? [])->unique()->values();
        if ($requestedRoles->contains('admin') && !$auth->isSuperAdmin()) {
            abort(403, 'Apenas super admin pode atribuir a role admin.');
        }

        $user = User::query()->findOrFail($id);
        if ($user->isSuperAdmin()) {
            abort(403, 'Super admin nao usa perfis (roles).');
        }
        // Always assign roles within the user's own tenant.
        // Superadmins can manage users across tenants, but we must avoid cross-tenant role leakage.
        $resolvedTenant = (string) ($user->tenant_uuid ?? '');
        if ($resolvedTenant === '') {
            abort(403, 'Selecione uma conta para atualizar perfis.');
        }
        if (!$auth->isSuperAdmin() && $tenantUuid && $user->tenant_uuid !== $tenantUuid) {
            abort(403, 'Usuário fora da conta atual.');
        }

        // Make role assignment deterministic even for newly created tenants (no roles seeded yet).
        $this->ensureTenantRoles($resolvedTenant);

        $roleIds = Role::query()
            ->where('tenant_uuid', $resolvedTenant)
            ->whereIn('name', $requestedRoles)
            ->pluck('id')
            ->all();

        $user->roles()->sync($roleIds);

        app(AdminAuditService::class)->log('admin.user.roles_updated', $user->id, [
            'tenant_uuid' => $resolvedTenant,
            'roles' => $requestedRoles->all(),
        ]);

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

        app(AdminAuditService::class)->log('admin.user.impersonate_started', $user->id, [
            'username' => $user->username,
        ]);

        return back()->with('status', "Impersonação ativa: {$user->name}.");
    }

    public function stopImpersonate()
    {
        $targetId = session('impersonate_user_id');
        session()->forget('impersonate_user_id');

        app(AdminAuditService::class)->log('admin.user.impersonate_stopped', $targetId ? (int) $targetId : null, []);

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

        app(AdminAuditService::class)->log('admin.user.promoted_to_admin', $user->id, [
            'tenant_uuid' => $user->tenant_uuid,
            'plan' => $data['plan'],
        ]);

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
