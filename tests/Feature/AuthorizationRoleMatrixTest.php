<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_operator_admin_permission_matrix(): void
    {
        [$tenant, $viewer, $operator, $admin] = $this->seedRoleMatrixUsers();

        // viewer: can view, cannot mutate
        $this->actingAs($viewer)
            ->get('/vault/explore')
            ->assertStatus(200);

        $this->actingAs($viewer)
            ->postJson('/vault/operational-records', ['name' => 'Viewer write'])
            ->assertForbidden();

        // operator: can mutate operational, cannot access admin users
        $this->actingAs($operator)
            ->postJson('/vault/operational-records', ['name' => 'Operator write', 'entity_type' => 'lead'])
            ->assertStatus(201);

        $this->actingAs($operator)
            ->get('/admin/users')
            ->assertForbidden();

        // admin: can mutate admin role permissions endpoint
        $adminRole = Role::query()->where('tenant_uuid', (string) $tenant->uuid)->where('name', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/roles/' . $adminRole->id . '/permissions', [
                'permissions' => ['leads.view', 'leads.merge'],
            ])
            ->assertStatus(302);
    }

    public function test_superadmin_global_is_read_only_but_can_still_impersonate(): void
    {
        [$tenant, $viewer] = $this->seedTenantUserOnly();
        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/vault/operational-records', ['name' => 'Blocked'])
            ->assertForbidden()
            ->assertJson([
                'ok' => false,
                'code' => 'superadmin_read_only',
            ]);

        $this->actingAs($superAdmin)
            ->post('/admin/users/' . $viewer->id . '/impersonate')
            ->assertStatus(302);
    }

    public function test_superadmin_impersonating_respects_impersonated_role_permissions(): void
    {
        [$tenant, $viewer, $operator] = $this->seedTenantUsersForImpersonation();
        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        // Impersonating viewer -> cannot mutate operational records.
        $this->actingAs($superAdmin)
            ->withSession(['impersonate_user_id' => $viewer->id])
            ->postJson('/vault/operational-records', ['name' => 'Should fail', 'entity_type' => 'lead'])
            ->assertForbidden();

        // Impersonating operator -> can mutate operational records.
        $this->actingAs($superAdmin)
            ->withSession(['impersonate_user_id' => $operator->id])
            ->postJson('/vault/operational-records', ['name' => 'Should pass', 'entity_type' => 'lead'])
            ->assertStatus(201);
    }

    /**
     * @return array{0:Tenant,1:User}
     */
    private function seedTenantUserOnly(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Role Matrix',
            'plan' => 'free',
            'slug' => 'tenant-role-matrix',
        ]);

        $viewer = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => false,
        ]);

        return [$tenant, $viewer];
    }

    /**
     * @return array{0:Tenant,1:User,2:User}
     */
    private function seedTenantUsersForImpersonation(): array
    {
        [$tenant, $viewer, $operator] = $this->seedRoleMatrixUsers();
        return [$tenant, $viewer, $operator];
    }

    /**
     * @return array{0:Tenant,1:User,2:User,3:User}
     */
    private function seedRoleMatrixUsers(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Role Matrix',
            'plan' => 'free',
            'slug' => 'tenant-role-matrix',
        ]);

        $permissions = [
            'leads.view',
            'leads.import',
            'leads.merge',
            'roles.manage',
            'users.manage',
            'system.settings',
            'automation.run',
            'automation.cancel',
            'automation.reprocess',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $viewerRole = Role::query()->firstOrCreate([
            'name' => 'viewer',
            'tenant_uuid' => (string) $tenant->uuid,
            'guard_name' => 'web',
        ]);
        $operatorRole = Role::query()->firstOrCreate([
            'name' => 'operator',
            'tenant_uuid' => (string) $tenant->uuid,
            'guard_name' => 'web',
        ]);
        $adminRole = Role::query()->firstOrCreate([
            'name' => 'admin',
            'tenant_uuid' => (string) $tenant->uuid,
            'guard_name' => 'web',
        ]);

        $viewerRole->syncPermissionsByName([
            'leads.view',
        ]);

        $operatorRole->syncPermissionsByName([
            'leads.view',
            'leads.import',
            'leads.merge',
            'automation.run',
            'automation.cancel',
            'automation.reprocess',
        ]);

        $adminRole->syncPermissionsByName($permissions);

        $viewer = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => false,
        ]);
        $operator = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => false,
        ]);
        $admin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => false,
        ]);

        $viewer->roles()->sync([$viewerRole->id]);
        $operator->roles()->sync([$operatorRole->id]);
        $admin->roles()->sync([$adminRole->id]);

        return [$tenant, $viewer, $operator, $admin];
    }
}
