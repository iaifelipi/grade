<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\TenantUserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_route_redirects_to_home_modal(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_new_tenant_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated('tenant');
        $response->assertRedirect(route('home', absolute: false));
        $this->assertDatabaseHas('tenant_users', [
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $tenantUser = TenantUser::query()->where('email', 'test@example.com')->firstOrFail();
        $tenant = Tenant::query()->findOrFail((int) $tenantUser->tenant_id);

        $ownerGroup = TenantUserGroup::query()
            ->where('tenant_id', (int) $tenant->id)
            ->where('slug', 'owner')
            ->first();

        $this->assertNotNull($ownerGroup);
        $this->assertSame((int) $ownerGroup->id, (int) $tenantUser->group_id);
        $this->assertDatabaseHas('tenant_user_groups', [
            'tenant_id' => (int) $tenant->id,
            'slug' => 'manager',
        ]);
        $this->assertDatabaseHas('tenant_user_groups', [
            'tenant_id' => (int) $tenant->id,
            'slug' => 'operator',
        ]);
        $this->assertDatabaseHas('tenant_user_groups', [
            'tenant_id' => (int) $tenant->id,
            'slug' => 'viewer',
        ]);
    }

    public function test_admin_registration_is_disabled_for_self_signup(): void
    {
        $response = $this->get('/admin/register');
        $response->assertRedirect(route('admin.login', absolute: false));

        $this->post('/admin/register', [
            'name' => 'Blocked Admin',
            'email' => 'blocked.admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(403);
    }
}
