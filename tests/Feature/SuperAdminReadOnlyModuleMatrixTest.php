<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminReadOnlyModuleMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_superadmin_blocks_mutations_in_admin_explore_and_vault(): void
    {
        [$tenant, $superAdmin] = $this->seedUsers();

        $this->actingAs($superAdmin)
            ->post('/admin/semantic/segments', ['name' => 'Teste'])
            ->assertStatus(302)
            ->assertSessionHas('error');

        $this->actingAs($superAdmin)
            ->post('/explore/columns/reset')
            ->assertStatus(302)
            ->assertSessionHas('error');

        $this->actingAs($superAdmin)
            ->postJson('/vault/operational-records', ['name' => 'Teste'])
            ->assertForbidden()
            ->assertJson([
                'ok' => false,
                'code' => 'superadmin_read_only',
            ]);
    }

    public function test_global_superadmin_allows_preview_post_without_persisting(): void
    {
        [$tenant, $superAdmin, $targetUser] = $this->seedUsers(withImpersonated: true);
        $sourceId = $this->seedSource((string) $tenant->uuid);

        $this->actingAs($superAdmin)
            ->post('/admin/users/' . $targetUser->id . '/impersonate')
            ->assertStatus(302);

        $this->actingAs($superAdmin)
            ->withSession(['impersonate_user_id' => $targetUser->id])
            ->post('/admin/impersonate/stop')
            ->assertStatus(302);

        $this->actingAs($superAdmin)
            ->postJson('/explore/data-quality/preview', [
                'source_id' => $sourceId,
                'column_key' => 'cpf',
                'rules' => ['only_numbers'],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_superadmin_impersonating_can_mutate_in_admin_explore_and_vault(): void
    {
        [$tenant, $superAdmin, $impersonated] = $this->seedUsers(withImpersonated: true);
        $sourceId = $this->seedSource((string) $tenant->uuid);

        $session = ['impersonate_user_id' => $impersonated->id];

        $this->actingAs($superAdmin)
            ->withSession($session)
            ->post('/admin/semantic/segments', ['name' => 'Segmento Impersonado'])
            ->assertStatus(302);

        $this->actingAs($superAdmin)
            ->withSession($session)
            ->post('/explore/columns/reset')
            ->assertStatus(302);

        $this->actingAs($superAdmin)
            ->withSession($session)
            ->post('/explore/data-quality/preview', [
                'source_id' => $sourceId,
                'column_key' => 'cpf',
                'rules' => ['only_numbers'],
            ])
            ->assertStatus(200);

        $this->actingAs($superAdmin)
            ->withSession($session)
            ->postJson('/vault/operational-records', [
                'source_id' => $sourceId,
                'name' => 'Registro Impersonado',
                'entity_type' => 'lead',
            ])
            ->assertStatus(201)
            ->assertJson(['ok' => true]);
    }

    /**
     * @return array{0:Tenant,1:User,2?:User}
     */
    private function seedUsers(bool $withImpersonated = false): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Matrix',
            'plan' => 'free',
            'slug' => 'tenant-matrix',
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        if (!$withImpersonated) {
            return [$tenant, $superAdmin];
        }

        Permission::query()->firstOrCreate(['name' => 'system.settings', 'guard_name' => 'web']);
        Permission::query()->firstOrCreate(['name' => 'leads.merge', 'guard_name' => 'web']);

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'admin',
            'tenant_uuid' => (string) $tenant->uuid,
            'guard_name' => 'web',
        ]);
        $adminRole->syncPermissionsByName(['system.settings', 'leads.merge']);

        $impersonated = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => false,
        ]);
        $impersonated->roles()->sync([$adminRole->id]);

        return [$tenant, $superAdmin, $impersonated];
    }

    private function seedSource(string $tenantUuid): int
    {
        $now = now();

        return (int) DB::table('lead_sources')->insertGetId([
            'tenant_uuid' => $tenantUuid,
            'parent_source_id' => null,
            'source_kind' => 'original',
            'original_name' => 'matrix.csv',
            'file_path' => $tenantUuid . '/imports/matrix.csv',
            'file_ext' => 'csv',
            'file_size_bytes' => 100,
            'file_hash' => sha1('matrix.csv'),
            'status' => 'done',
            'processed_rows' => 0,
            'inserted_rows' => 0,
            'skipped_rows' => 0,
            'progress_percent' => 100,
            'mapping_json' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
