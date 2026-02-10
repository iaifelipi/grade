<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GuestIdentityService;
use App\Services\GuestTenantTransferService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, GuestTenantTransferService $transferService): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($request) {
            $baseSlug = \Illuminate\Support\Str::slug((string) $request->name);
            $slug = $baseSlug;
            if ($slug) {
                $exists = Tenant::query()->where('slug', $slug)->exists();
                if ($exists) {
                    $slug = $slug . '-' . \Illuminate\Support\Str::random(4);
                }
            }

            $tenant = Tenant::create([
                'name' => $request->name,
                'plan' => 'free',
                'slug' => $slug,
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'tenant_uuid' => $tenant->uuid,
            ]);

            $operatorRole = $this->ensureTenantRoles($tenant->uuid);
            if ($operatorRole) {
                $user->roles()->syncWithoutDetaching([$operatorRole->id]);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $clearGuestCookie = false;

        $guestUuid = (string) $request->session()->get('guest_tenant_uuid', '');
        $userTenantUuid = (string) ($user->tenant_uuid ?? '');
        if ($guestUuid && $userTenantUuid && $guestUuid !== $userTenantUuid) {
            $transferService->transfer($guestUuid, $userTenantUuid, $user->id);
            $request->session()->forget('guest_tenant_uuid');
            $clearGuestCookie = true;
        }
        if ($userTenantUuid) {
            $request->session()->put('tenant_uuid', $userTenantUuid);
        }

        $response = redirect(route('home', absolute: false));
        if ($clearGuestCookie) {
            $response->withCookie(Cookie::forget(GuestIdentityService::COOKIE_NAME));
        }

        return $response;
    }

    private function ensureTenantRoles(string $tenantUuid): ?Role
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

        $operator = Role::firstOrCreate([
            'name' => 'operator',
            'guard_name' => 'web',
            'tenant_uuid' => $tenantUuid,
        ]);

        $viewer = Role::firstOrCreate([
            'name' => 'viewer',
            'guard_name' => 'web',
            'tenant_uuid' => $tenantUuid,
        ]);

        $admin->syncPermissionsByName(Permission::query()->pluck('name')->all());

        $operator->syncPermissionsByName([
            'leads.import',
            'leads.view',
            'leads.export',
            'leads.normalize',
            'leads.merge',
            'automation.run',
            'automation.reprocess',
            'analytics.view',
        ]);

        $viewer->syncPermissionsByName([
            'leads.view',
            'analytics.view',
        ]);

        return $operator;
    }
}
