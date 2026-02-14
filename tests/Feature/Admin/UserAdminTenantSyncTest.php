<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdminTenantSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_and_update_syncs_tenant_user_via_admin_routes(): void
    {
        [$tenant, $manager] = $this->seedManagerWithUsersManagePermission();

        $this->actingAs($manager, 'web')
            ->post(route('admin.users.store'), [
                'name' => 'Sync Target',
                'email' => 'sync.target@example.com',
                'email_confirmation' => 'sync.target@example.com',
                'username' => 'sync_target',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'locale' => 'pt-BR',
                'timezone' => 'America/Sao_Paulo',
                'status' => 'active',
            ])
            ->assertStatus(302);

        $created = User::query()->where('email', 'sync.target@example.com')->first();
        $this->assertNotNull($created);

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => (int) $tenant->id,
            'tenant_uuid' => (string) $tenant->uuid,
            'source_user_id' => (int) $created->id,
            'email' => 'sync.target@example.com',
            'username' => 'sync_target',
            'status' => 'active',
        ]);

        $this->actingAs($manager, 'web')
            ->put(route('admin.users.update', ['id' => $created->id]), [
                'name' => 'Sync Target Updated',
                'email' => 'sync.target.updated@example.com',
                'username' => 'sync_target',
                'password' => '',
                'password_confirmation' => '',
                'locale' => 'pt-BR',
                'timezone' => 'America/Sao_Paulo',
                'status' => 'active',
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('users', [
            'id' => (int) $created->id,
            'email' => 'sync.target.updated@example.com',
            'name' => 'Sync Target Updated',
        ]);

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => (int) $tenant->id,
            'source_user_id' => (int) $created->id,
            'email' => 'sync.target.updated@example.com',
            'name' => 'Sync Target Updated',
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('tenant_users', [
            'tenant_id' => (int) $tenant->id,
            'email' => 'sync.target@example.com',
        ]);
    }

    public function test_disable_enable_and_destroy_keep_tenant_user_in_sync(): void
    {
        [$tenant, $manager] = $this->seedManagerWithUsersManagePermission();

        $this->actingAs($manager, 'web')
            ->post(route('admin.users.store'), [
                'name' => 'Lifecycle Target',
                'email' => 'lifecycle.target@example.com',
                'email_confirmation' => 'lifecycle.target@example.com',
                'username' => 'lifecycle_target',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'locale' => 'pt-BR',
                'timezone' => 'America/Sao_Paulo',
                'status' => 'active',
            ])
            ->assertStatus(302);

        $target = User::query()->where('email', 'lifecycle.target@example.com')->firstOrFail();

        $this->actingAs($manager, 'web')
            ->post(route('admin.users.disable', ['id' => $target->id]))
            ->assertStatus(302);

        $this->assertDatabaseHas('users', [
            'id' => (int) $target->id,
        ]);
        $this->assertNotNull(User::query()->findOrFail($target->id)->disabled_at);

        $disabledTenantUser = TenantUser::query()
            ->where('source_user_id', (int) $target->id)
            ->firstOrFail();

        $this->assertSame('disabled', (string) $disabledTenantUser->status);
        $this->assertNotNull($disabledTenantUser->deactivate_at);

        $this->actingAs($manager, 'web')
            ->post(route('admin.users.enable', ['id' => $target->id]))
            ->assertStatus(302);

        $enabledTenantUser = TenantUser::query()
            ->where('source_user_id', (int) $target->id)
            ->firstOrFail();

        $this->assertSame('active', (string) $enabledTenantUser->status);
        $this->assertNull($enabledTenantUser->deactivate_at);
        $this->assertSame((int) $target->id, (int) $enabledTenantUser->source_user_id);

        $this->actingAs($manager, 'web')
            ->delete(route('admin.users.destroy', ['id' => $target->id]))
            ->assertStatus(302);

        $this->assertDatabaseMissing('users', [
            'id' => (int) $target->id,
        ]);

        $this->assertSoftDeleted('tenant_users', [
            'tenant_id' => (int) $tenant->id,
            'email' => 'lifecycle.target@example.com',
        ]);
    }

    /**
     * @return array{0:Tenant,1:User}
     */
    private function seedManagerWithUsersManagePermission(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Sync HTTP',
            'plan' => 'free',
            'slug' => 'tenant-sync-http',
        ]);

        Permission::query()->firstOrCreate([
            'name' => 'users.manage',
            'guard_name' => 'web',
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'admin',
            'tenant_uuid' => (string) $tenant->uuid,
            'guard_name' => 'web',
        ]);
        $role->syncPermissionsByName(['users.manage']);

        $manager = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => false,
        ]);
        $manager->roles()->sync([$role->id]);

        return [$tenant, $manager];
    }
}
