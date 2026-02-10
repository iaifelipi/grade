<?php

namespace Tests\Feature\LeadsVault;

use App\Jobs\ImportLeadSourceJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SourceReprocessTargetsRootTest extends TestCase
{
    use RefreshDatabase;

    public function test_reprocessing_a_derived_source_targets_the_root_source(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Teste',
            'plan' => 'free',
            'slug' => 'tenant-teste',
        ]);

        $user = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $now = now();

        $rootId = (int) DB::table('lead_sources')->insertGetId([
            'tenant_uuid' => (string) $tenant->uuid,
            'parent_source_id' => null,
            'source_kind' => 'original',
            'original_name' => 'base.csv',
            'file_path' => (string) $tenant->uuid . '/imports/base.csv',
            'file_ext' => 'csv',
            'file_size_bytes' => 120,
            'file_hash' => sha1('base.csv'),
            'status' => 'done',
            'processed_rows' => 20,
            'inserted_rows' => 18,
            'skipped_rows' => 2,
            'progress_percent' => 100,
            'mapping_json' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $derivedId = (int) DB::table('lead_sources')->insertGetId([
            'tenant_uuid' => (string) $tenant->uuid,
            'parent_source_id' => $rootId,
            'source_kind' => 'edited',
            'original_name' => 'base.csv',
            'file_path' => (string) $tenant->uuid . '/imports/base-editado.csv',
            'file_ext' => 'csv',
            'file_size_bytes' => 121,
            'file_hash' => sha1('base-editado.csv'),
            'status' => 'done',
            'processed_rows' => 20,
            'inserted_rows' => 20,
            'skipped_rows' => 0,
            'progress_percent' => 100,
            'mapping_json' => '[]',
            'created_at' => $now->copy()->addSecond(),
            'updated_at' => $now->copy()->addSecond(),
        ]);

        Queue::fake();

        $this->actingAs($user)
            ->withSession(['impersonate_user_id' => $user->id])
            ->postJson('/vault/sources/' . $derivedId . '/reprocess')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('lead_sources', [
            'id' => $rootId,
            'status' => 'queued',
            'processed_rows' => 0,
            'inserted_rows' => 0,
            'skipped_rows' => 0,
            'progress_percent' => 0,
        ]);

        $this->assertDatabaseHas('lead_sources', [
            'id' => $derivedId,
            'status' => 'done',
        ]);

        Queue::assertPushed(ImportLeadSourceJob::class, function (ImportLeadSourceJob $job) use ($rootId) {
            return (int) $job->sourceId === $rootId;
        });
    }
}
