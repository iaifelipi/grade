<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminReadOnlyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_superadmin_can_run_data_quality_preview(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Preview',
            'plan' => 'free',
            'slug' => 'tenant-preview',
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $now = now();
        $sourceId = (int) DB::table('lead_sources')->insertGetId([
            'tenant_uuid' => (string) $tenant->uuid,
            'parent_source_id' => null,
            'source_kind' => 'original',
            'original_name' => 'preview.csv',
            'file_path' => (string) $tenant->uuid . '/imports/preview.csv',
            'file_ext' => 'csv',
            'file_size_bytes' => 120,
            'file_hash' => sha1('preview.csv'),
            'status' => 'done',
            'processed_rows' => 0,
            'inserted_rows' => 0,
            'skipped_rows' => 0,
            'progress_percent' => 100,
            'mapping_json' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/explore/data-quality/preview', [
                'source_id' => $sourceId,
                'column_key' => 'cpf',
                'rules' => ['only_numbers'],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_global_superadmin_still_cannot_write_in_read_only_mode(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Readonly',
            'plan' => 'free',
            'slug' => 'tenant-readonly',
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/vault/operational-records', [
                'name' => 'Teste Bloqueio',
            ])
            ->assertForbidden()
            ->assertJson([
                'ok' => false,
                'code' => 'superadmin_read_only',
            ]);
    }
}

