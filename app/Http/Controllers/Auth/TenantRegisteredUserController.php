<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\GuestIdentityService;
use App\Services\GuestTenantTransferService;
use App\Services\TenantUsers\TenantUserGroupProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class TenantRegisteredUserController extends Controller
{
    public function store(
        Request $request,
        GuestTenantTransferService $transferService,
        TenantUserGroupProvisioningService $groupProvisioning
    ): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:190'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        /** @var TenantUser $tenantUser */
        $tenantUser = DB::transaction(function () use ($request, $groupProvisioning) {
            $tenantName = trim((string) $request->input('name', ''));
            $email = mb_strtolower(trim((string) $request->input('email', '')));

            $baseSlug = Str::slug($tenantName);
            $slug = $baseSlug !== '' ? $baseSlug : Str::random(8);
            while (Tenant::query()->where('slug', $slug)->exists()) {
                $slug = ($baseSlug !== '' ? $baseSlug : 'tenant') . '-' . Str::lower(Str::random(4));
            }

            $tenant = Tenant::query()->create([
                'name' => $tenantName,
                'plan' => 'free',
                'slug' => $slug,
            ]);

            $groups = $groupProvisioning->provisionForPlan($tenant, (string) $tenant->plan);
            $ownerGroupId = (int) ($groups->get('owner')?->id ?? 0);

            $tenantId = (int) $tenant->id;
            $tenantUuid = (string) $tenant->uuid;

            $usernameBase = Str::lower((string) Str::before($email, '@'));
            $usernameBase = preg_replace('/[^a-z0-9._-]+/', '', $usernameBase) ?: 'user';
            $username = $usernameBase;
            $suffix = 1;

            while (TenantUser::query()->where('tenant_id', $tenantId)->where('username', $username)->whereNull('deleted_at')->exists()) {
                $suffix++;
                $username = $usernameBase . $suffix;
            }

            return TenantUser::query()->create([
                'tenant_id' => $tenantId,
                'tenant_uuid' => $tenantUuid,
                'group_id' => $ownerGroupId > 0 ? $ownerGroupId : null,
                'name' => $tenantName,
                'email' => $email,
                'username' => $username,
                'password' => Hash::make((string) $request->input('password')),
                'status' => 'active',
                'is_owner' => true,
                'is_primary_account' => true,
                'accepted_at' => now(),
            ]);
        });

        Auth::guard('tenant')->login($tenantUser);

        $request->session()->regenerate();
        $request->session()->put('tenant_uuid', (string) $tenantUser->tenant_uuid);

        $clearGuestCookie = false;
        $guestUuid = (string) $request->session()->get('guest_tenant_uuid', '');
        $tenantUuid = (string) $tenantUser->tenant_uuid;

        if ($guestUuid !== '' && $tenantUuid !== '' && $guestUuid !== $tenantUuid) {
            $transferService->transfer($guestUuid, $tenantUuid, null);
            $request->session()->forget('guest_tenant_uuid');
            $clearGuestCookie = true;
        }

        app()->instance('tenant_uuid', $tenantUuid);

        $response = redirect()->intended(route('home', absolute: false));

        if ($clearGuestCookie) {
            $response->withCookie(Cookie::forget(GuestIdentityService::COOKIE_NAME));
        }

        return $response;
    }
}
