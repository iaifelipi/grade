<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_login_route_redirects_to_home_modal(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    public function test_tenant_users_can_authenticate_using_the_login_screen(): void
    {
        [$tenant, $tenantUser] = $this->seedTenantUser();

        $response = $this->post('/login', [
            'tenant' => (string) $tenant->slug,
            'login' => $tenantUser->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated('tenant');
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_tenant_users_can_authenticate_without_tenant_when_login_is_unique(): void
    {
        [, $tenantUser] = $this->seedTenantUser();

        $response = $this->post('/login', [
            'login' => $tenantUser->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated('tenant');
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_tenant_users_can_authenticate_using_username(): void
    {
        [$tenant, $tenantUser] = $this->seedTenantUser(username: 'user_login_test');

        $response = $this->post('/login', [
            'tenant' => (string) $tenant->slug,
            'login' => 'user_login_test',
            'password' => 'password',
        ]);

        $this->assertAuthenticated('tenant');
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_tenant_users_can_not_authenticate_with_invalid_password(): void
    {
        [$tenant, $tenantUser] = $this->seedTenantUser();

        $this->post('/login', [
            'tenant' => (string) $tenant->slug,
            'login' => $tenantUser->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest('tenant');
    }

    public function test_web_users_can_authenticate_using_admin_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/admin/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated('web');
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_web_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->post('/logout');

        $this->assertGuest('web');
        $response->assertRedirect(route('home', ['logged_out' => 1], absolute: false));
    }

    public function test_tenant_users_can_logout(): void
    {
        [, $tenantUser] = $this->seedTenantUser();

        $response = $this->actingAs($tenantUser, 'tenant')->post('/tenant/logout');

        $this->assertGuest('tenant');
        $response->assertRedirect(route('login', absolute: false));
    }

    /**
     * @return array{0: Tenant, 1: TenantUser}
     */
    private function seedTenantUser(?string $username = null): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Auth Test',
            'plan' => 'free',
            'slug' => 'tenant-auth-test',
        ]);

        $tenantUser = TenantUser::query()->create([
            'tenant_id' => (int) $tenant->id,
            'tenant_uuid' => (string) $tenant->uuid,
            'name' => 'Tenant User',
            'email' => 'tenant.auth@example.com',
            'username' => $username ?? 'tenant_auth_test',
            'password' => 'password',
            'status' => 'active',
        ]);

        return [$tenant, $tenantUser];
    }
}
