<?php

namespace Tests\Unit\Users;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Users\UserTenantSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTenantSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_tenant_user_from_web_user(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Sync',
            'plan' => 'free',
            'slug' => 'tenant-sync',
        ]);

        $user = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'name' => 'John Sync',
            'email' => 'john.sync@example.com',
            'username' => 'johnsync',
            'disabled_at' => null,
        ]);

        $tenantUser = app(UserTenantSyncService::class)->syncFromUser($user);

        $this->assertSame((int) $tenant->id, (int) $tenantUser->tenant_id);
        $this->assertSame((string) $tenant->uuid, (string) $tenantUser->tenant_uuid);
        $this->assertSame('john.sync@example.com', (string) $tenantUser->email);
        $this->assertSame('johnsync', (string) $tenantUser->username);
        $this->assertSame('active', (string) $tenantUser->status);
        $this->assertNull($tenantUser->deactivate_at);
        $this->assertSame((int) $user->id, (int) $tenantUser->source_user_id);

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => (int) $tenant->id,
            'source_user_id' => (int) $user->id,
            'email' => 'john.sync@example.com',
            'status' => 'active',
        ]);
    }

    public function test_sync_updates_existing_tenant_user_and_disables_when_web_user_is_disabled(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Sync Update',
            'plan' => 'free',
            'slug' => 'tenant-sync-update',
        ]);

        $user = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'name' => 'Mary Updated',
            'email' => 'mary.updated@example.com',
            'username' => 'maryupdated',
            'disabled_at' => now(),
        ]);

        $existing = TenantUser::query()->create([
            'tenant_id' => (int) $tenant->id,
            'tenant_uuid' => (string) $tenant->uuid,
            'name' => 'Old Name',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'mary.updated@example.com',
            'username' => 'oldusername',
            'password' => 'password',
            'status' => 'active',
            'accepted_at' => now(),
            'deactivate_at' => null,
        ]);

        $tenantUser = app(UserTenantSyncService::class)->syncFromUser($user);

        $this->assertSame((int) $existing->id, (int) $tenantUser->id);
        $this->assertSame('Mary Updated', (string) $tenantUser->name);
        $this->assertSame('maryupdated', (string) $tenantUser->username);
        $this->assertSame('disabled', (string) $tenantUser->status);
        $this->assertNotNull($tenantUser->deactivate_at);
        $this->assertSame((int) $user->id, (int) $tenantUser->source_user_id);
    }

    public function test_delete_synced_tenant_user_soft_deletes_record(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Sync Delete',
            'plan' => 'free',
            'slug' => 'tenant-sync-delete',
        ]);

        $user = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'name' => 'Delete Me',
            'email' => 'delete.me@example.com',
            'username' => 'deleteme',
        ]);

        $tenantUser = TenantUser::query()->create([
            'tenant_id' => (int) $tenant->id,
            'tenant_uuid' => (string) $tenant->uuid,
            'name' => 'Delete Me',
            'email' => 'delete.me@example.com',
            'username' => 'deleteme',
            'password' => 'password',
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        app(UserTenantSyncService::class)->deleteSyncedTenantUser($user);

        $this->assertSoftDeleted('tenant_users', [
            'id' => (int) $tenantUser->id,
            'source_user_id' => (int) $user->id,
        ]);
    }
}
