<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteTenantUserRequest;
use App\Http\Requests\Admin\StoreTenantUserRequest;
use App\Http\Requests\Admin\UpdateTenantUserRequest;
use App\Models\PricePlan;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\TenantUserGroup;
use App\Services\TenantUsers\TenantUserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantUserAdminController extends Controller
{
    public function index(Request $request): View
    {
        $isGlobalSuper = $this->isGlobalSuperAdminContext();
        $selectedTenantUuid = trim((string) $request->query('tenant_uuid', ''));

        $tenant = null;
        $tenantUsersQuery = TenantUser::query()
            ->with(['group', 'tenant', 'pricePlan'])
            ->orderByDesc('id');

        $tenants = collect();
        $groups = collect();
        $pricePlans = PricePlan::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active']);

        if ($isGlobalSuper) {
            $tenants = Tenant::query()
                ->orderBy('name')
                ->get(['id', 'uuid', 'slug', 'name', 'plan']);

            if ($selectedTenantUuid !== '') {
                $tenant = Tenant::query()->where('uuid', $selectedTenantUuid)->first();
                if ($tenant) {
                    $tenantUsersQuery->where('tenant_id', $tenant->id);
                }
            }

            $groups = TenantUserGroup::query()
                ->with('tenant:id,uuid,slug,name')
                ->where('is_active', true)
                ->orderBy('tenant_id')
                ->orderBy('name')
                ->get();
        } else {
            $tenant = $this->resolveTenantFromContext();
            $tenantUsersQuery->where('tenant_id', $tenant->id);

            $tenants = Tenant::query()
                ->where('id', $tenant->id)
                ->get(['id', 'uuid', 'slug', 'name', 'plan']);

            $groups = TenantUserGroup::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $tenantUsers = $tenantUsersQuery
            ->paginate(20)
            ->withQueryString();

        return view('admin.tenant-users.index', [
            'tenant' => $tenant,
            'tenantUsers' => $tenantUsers,
            'groups' => $groups,
            'isGlobalSuper' => $isGlobalSuper,
            'tenants' => $tenants,
            'pricePlans' => $pricePlans,
            'selectedTenantUuid' => $selectedTenantUuid,
        ]);
    }

    public function store(StoreTenantUserRequest $request): RedirectResponse
    {
        $tenant = $this->resolveTenantForWrite($request);
        $data = $request->validated();

        $groupId = $this->normalizeGroup($tenant->id, $data['group_id'] ?? null);
        $pricePlanId = $this->resolvePricePlanId($data);

        $tenantUser = new TenantUser();
        $tenantUser->tenant_id = $tenant->id;
        $tenantUser->tenant_uuid = $tenant->uuid;
        $tenantUser->group_id = $groupId;
        $tenantUser->price_plan_id = $pricePlanId;
        $tenantUser->first_name = $data['first_name'];
        $tenantUser->last_name = $data['last_name'] ?? null;
        $tenantUser->name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $tenantUser->email = mb_strtolower((string) $data['email']);
        $tenantUser->username = $this->normalizeUsername($data['username'] ?? null, $data['email']);
        $tenantUser->password = Hash::make((string) $data['password']);
        $tenantUser->phone_e164 = $data['phone_e164'] ?? null;
        $tenantUser->birth_date = $data['birth_date'] ?? null;
        $tenantUser->locale = $data['locale'] ?? 'en';
        $tenantUser->timezone = $data['timezone'] ?? 'UTC';
        $tenantUser->status = $data['status'];
        $tenantUser->is_primary_account = (bool) ($data['is_primary_account'] ?? false);
        $tenantUser->send_welcome_email = (bool) ($data['send_welcome_email'] ?? true);
        $tenantUser->must_change_password = (bool) ($data['must_change_password'] ?? false);
        $tenantUser->deactivate_at = !empty($data['deactivate_at']) ? $data['deactivate_at'] : ($data['status'] === 'disabled' ? now() : null);
        $tenantUser->avatar_path = $data['avatar_path'] ?? null;
        $tenantUser->email_verified_at = now();
        $tenantUser->accepted_at = $data['status'] === 'active' ? now() : null;
        $tenantUser->save();

        return back()->with('status', 'Tenant user created.');
    }

    public function update(UpdateTenantUserRequest $request, int $id): RedirectResponse
    {
        $tenantUser = $this->resolveTenantUserForWrite($id);

        $data = $request->validated();
        $pricePlanId = $this->resolvePricePlanId($data);
        $tenantUser->group_id = $this->normalizeGroup((int) $tenantUser->tenant_id, $data['group_id'] ?? null);
        $tenantUser->price_plan_id = $pricePlanId;
        $tenantUser->first_name = $data['first_name'];
        $tenantUser->last_name = $data['last_name'] ?? null;
        $tenantUser->name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $tenantUser->email = mb_strtolower((string) $data['email']);
        $tenantUser->username = $this->normalizeUsername($data['username'] ?? null, $data['email']);
        $tenantUser->phone_e164 = $data['phone_e164'] ?? null;
        $tenantUser->birth_date = $data['birth_date'] ?? null;
        $tenantUser->locale = $data['locale'] ?? $tenantUser->locale;
        $tenantUser->timezone = $data['timezone'] ?? $tenantUser->timezone;
        $tenantUser->status = $data['status'];
        $tenantUser->is_primary_account = (bool) ($data['is_primary_account'] ?? false);
        $tenantUser->send_welcome_email = (bool) ($data['send_welcome_email'] ?? false);
        $tenantUser->must_change_password = (bool) ($data['must_change_password'] ?? false);
        $tenantUser->deactivate_at = !empty($data['deactivate_at']) ? $data['deactivate_at'] : ($data['status'] === 'disabled' ? now() : null);
        $tenantUser->avatar_path = $data['avatar_path'] ?? null;

        if (!empty($data['password'])) {
            $tenantUser->password = Hash::make((string) $data['password']);
            $tenantUser->must_change_password = false;
        }

        if ($data['status'] === 'active') {
            $tenantUser->deactivate_at = null;
            $tenantUser->accepted_at = $tenantUser->accepted_at ?: now();
            $tenantUser->email_verified_at = $tenantUser->email_verified_at ?: now();
        }

        $tenantUser->save();

        return back()->with('status', 'Tenant user updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantUser = $this->resolveTenantUserForWrite($id);
        $tenantUser->delete();

        return back()->with('status', 'Tenant user removed.');
    }

    public function invite(InviteTenantUserRequest $request, TenantUserInvitationService $service): RedirectResponse
    {
        $tenant = $this->resolveTenantForWrite($request);
        $data = $request->validated();

        $result = $service->create(
            tenant: $tenant,
            email: (string) $data['email'],
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            groupId: isset($data['group_id']) ? (int) $data['group_id'] : null,
            inviterUserId: auth()->id(),
            inviterTenantUserId: null,
            sendEmail: (bool) ($data['send_email'] ?? true),
            expiresInHours: (int) ($data['expires_in_hours'] ?? 48)
        );

        return back()
            ->with('status', 'Tenant invitation created.')
            ->with('tenant_invite_url', $result['url']);
    }

    public function impersonate(int $id): RedirectResponse
    {
        $tenantUser = $this->resolveTenantUserForWrite($id);

        if ($tenantUser->status !== 'active' || !empty($tenantUser->deactivate_at)) {
            return back()->with('error', 'Tenant user must be active to impersonate.');
        }

        Auth::guard('tenant')->login($tenantUser);
        session(['tenant_uuid' => (string) $tenantUser->tenant_uuid]);

        return redirect()->route('home')
            ->with('status', 'Impersonation started for tenant user: ' . ($tenantUser->name ?: $tenantUser->email));
    }

    private function isGlobalSuperAdminContext(): bool
    {
        return auth()->check()
            && auth()->user()?->isSuperAdmin()
            && !session()->has('impersonate_user_id');
    }

    private function resolveTenantForWrite(Request $request): Tenant
    {
        if ($this->isGlobalSuperAdminContext()) {
            $tenantUuid = trim((string) $request->input('tenant_uuid', $request->query('tenant_uuid', '')));
            if ($tenantUuid === '') {
                abort(422, 'Selecione um tenant para esta operação.');
            }

            return Tenant::query()->where('uuid', $tenantUuid)->firstOrFail();
        }

        return $this->resolveTenantFromContext();
    }

    private function resolveTenantUserForWrite(int $id): TenantUser
    {
        $query = TenantUser::query()->with('tenant');

        if (!$this->isGlobalSuperAdminContext()) {
            $tenant = $this->resolveTenantFromContext();
            $query->where('tenant_id', $tenant->id);
        }

        return $query->findOrFail($id);
    }

    private function resolveTenantFromContext(): Tenant
    {
        $tenantUuid = app()->bound('tenant_uuid') ? (string) app('tenant_uuid') : '';
        if ($tenantUuid === '') {
            abort(403, 'Tenant context not selected.');
        }

        return Tenant::query()->where('uuid', $tenantUuid)->firstOrFail();
    }

    private function normalizeGroup(int $tenantId, ?int $groupId): ?int
    {
        if (!$groupId) {
            return null;
        }

        return TenantUserGroup::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $groupId)
            ->value('id');
    }

    private function normalizeUsername(?string $username, string $email): ?string
    {
        $raw = trim((string) $username);
        if ($raw !== '') {
            return Str::lower($raw);
        }

        $base = Str::before(trim(mb_strtolower($email)), '@');
        $base = preg_replace('/[^a-z0-9._-]/', '', $base ?: '') ?: 'tenantuser';

        return Str::limit($base, 64, '');
    }

    /**
     * @param array<string,mixed> $data
     */
    private function resolvePricePlanId(array $data): ?int
    {
        $pricePlanId = !empty($data['price_plan_id']) ? (int) $data['price_plan_id'] : null;

        if ($pricePlanId) {
            return (int) $pricePlanId;
        }

        return null;
    }
}
