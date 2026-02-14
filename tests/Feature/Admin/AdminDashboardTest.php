<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_access_dashboard_and_filter_by_tenant(): void
    {
        $tenantA = Tenant::query()->create([
            'name' => 'Tenant Alpha',
            'plan' => 'free',
            'slug' => 'tenant-alpha',
        ]);
        $tenantB = Tenant::query()->create([
            'name' => 'Tenant Beta',
            'plan' => 'pro',
            'slug' => 'tenant-beta',
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenantA->uuid,
            'is_super_admin' => true,
        ]);

        TenantUser::query()->create([
            'tenant_id' => (int) $tenantA->id,
            'tenant_uuid' => (string) $tenantA->uuid,
            'name' => 'Alpha Customer',
            'email' => 'alpha.customer@example.com',
            'username' => 'alpha_customer',
            'password' => 'password',
            'status' => 'active',
        ]);

        TenantUser::query()->create([
            'tenant_id' => (int) $tenantB->id,
            'tenant_uuid' => (string) $tenantB->uuid,
            'name' => 'Beta Customer',
            'email' => 'beta.customer@example.com',
            'username' => 'beta_customer',
            'password' => 'password',
            'status' => 'active',
        ]);

        $response = $this->actingAs($superAdmin, 'web')
            ->get(route('admin.dashboard', [
                'tenant_uuid' => (string) $tenantA->uuid,
                'period' => 30,
            ]));

        $response->assertStatus(200)
            ->assertSee('Dashboard Admin')
            ->assertViewHas('selectedTenantUuid', (string) $tenantA->uuid)
            ->assertViewHas('periodDays', 30)
            ->assertViewHas('kpis', function (array $kpis): bool {
                return (int) ($kpis['customers_active'] ?? 0) === 1;
            });
    }

    public function test_tenant_admin_scope_ignores_foreign_tenant_filter(): void
    {
        $tenantA = Tenant::query()->create([
            'name' => 'Tenant Scope A',
            'plan' => 'free',
            'slug' => 'tenant-scope-a',
        ]);
        $tenantB = Tenant::query()->create([
            'name' => 'Tenant Scope B',
            'plan' => 'free',
            'slug' => 'tenant-scope-b',
        ]);

        Permission::query()->firstOrCreate([
            'name' => 'users.manage',
            'guard_name' => 'web',
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'admin',
            'tenant_uuid' => (string) $tenantA->uuid,
            'guard_name' => 'web',
        ]);
        $role->syncPermissionsByName(['users.manage']);

        $manager = User::factory()->create([
            'tenant_uuid' => (string) $tenantA->uuid,
            'is_super_admin' => false,
        ]);
        $manager->roles()->sync([$role->id]);

        TenantUser::query()->create([
            'tenant_id' => (int) $tenantA->id,
            'tenant_uuid' => (string) $tenantA->uuid,
            'name' => 'Scope A User',
            'email' => 'scope.a@example.com',
            'username' => 'scope_a_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        TenantUser::query()->create([
            'tenant_id' => (int) $tenantB->id,
            'tenant_uuid' => (string) $tenantB->uuid,
            'name' => 'Scope B User',
            'email' => 'scope.b@example.com',
            'username' => 'scope_b_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager, 'web')
            ->get(route('admin.dashboard', [
                'tenant_uuid' => (string) $tenantB->uuid,
                'period' => 90,
            ]));

        $response->assertStatus(200)
            ->assertViewHas('selectedTenantUuid', (string) $tenantA->uuid)
            ->assertViewHas('periodDays', 90)
            ->assertViewHas('kpis', function (array $kpis): bool {
                return (int) ($kpis['customers_active'] ?? 0) === 1;
            });
    }
}

