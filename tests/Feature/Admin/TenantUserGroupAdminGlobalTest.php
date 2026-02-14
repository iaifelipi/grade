<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\TenantUserGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantUserGroupAdminGlobalTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_list_groups_globally_and_filter_by_tenant(): void
    {
        [$tenantA, $groupA, $tenantB, $groupB, $superAdmin] = $this->seedGlobalGroupsScenario();

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.tenantUserGroups.index'))
            ->assertStatus(200)
            ->assertSee($groupA->name)
            ->assertSee($groupB->name);

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.tenantUserGroups.index', ['tenant_uuid' => (string) $tenantA->uuid]))
            ->assertStatus(200)
            ->assertSee($groupA->name)
            ->assertDontSee($groupB->name);

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.tenantUserGroups.index', ['tenant_uuid' => (string) $tenantB->uuid]))
            ->assertStatus(200)
            ->assertSee($groupB->name)
            ->assertDontSee($groupA->name);
    }

    public function test_superadmin_can_update_group_permissions_without_tenant_override(): void
    {
        [$tenantA, $groupA, , , $superAdmin] = $this->seedGlobalGroupsScenario();

        $this->actingAs($superAdmin, 'web')
            ->put(route('admin.tenantUserGroups.permissions.update', ['id' => $groupA->id]), [
                'tenant_uuid' => (string) $tenantA->uuid,
                'permissions' => ['imports.manage', 'exports.view'],
            ])
            ->assertStatus(302)
            ->assertSessionHas('status');

        $updated = TenantUserGroup::query()->findOrFail($groupA->id);
        $this->assertSame(['imports.manage', 'exports.view'], array_values($updated->permissions_json ?? []));

        // wildcard keeps only '*' for deterministic behavior
        $this->actingAs($superAdmin, 'web')
            ->put(route('admin.tenantUserGroups.permissions.update', ['id' => $groupA->id]), [
                'tenant_uuid' => (string) $tenantA->uuid,
                'permissions' => ['*', 'imports.manage'],
            ])
            ->assertStatus(302);

        $updatedWildcard = TenantUserGroup::query()->findOrFail($groupA->id);
        $this->assertSame(['*'], array_values($updatedWildcard->permissions_json ?? []));
    }

    /**
     * @return array{0:Tenant,1:TenantUserGroup,2:Tenant,3:TenantUserGroup,4:User}
     */
    private function seedGlobalGroupsScenario(): array
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

        $groupA = TenantUserGroup::query()->create([
            'tenant_id' => (int) $tenantA->id,
            'tenant_uuid' => (string) $tenantA->uuid,
            'name' => 'Alpha Operators',
            'slug' => 'alpha-operators',
            'is_default' => false,
            'is_active' => true,
            'permissions_json' => ['imports.manage'],
        ]);

        $groupB = TenantUserGroup::query()->create([
            'tenant_id' => (int) $tenantB->id,
            'tenant_uuid' => (string) $tenantB->uuid,
            'name' => 'Beta Viewers',
            'slug' => 'beta-viewers',
            'is_default' => false,
            'is_active' => true,
            'permissions_json' => ['exports.view'],
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenantA->uuid,
            'is_super_admin' => true,
        ]);

        return [$tenantA, $groupA, $tenantB, $groupB, $superAdmin];
    }
}
