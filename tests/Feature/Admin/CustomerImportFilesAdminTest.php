<?php

namespace Tests\Feature\Admin;

use App\Jobs\ImportLeadSourceJob;
use App\Models\LeadSource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerImportFilesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_list_files_globally_and_filter_by_tenant(): void
    {
        $tenantA = Tenant::query()->create([
            'name' => 'Tenant Alpha',
            'plan' => 'free',
            'slug' => 'tenant-alpha-files',
        ]);

        $tenantB = Tenant::query()->create([
            'name' => 'Tenant Beta',
            'plan' => 'pro',
            'slug' => 'tenant-beta-files',
        ]);

        LeadSource::query()->create([
            'tenant_uuid' => (string) $tenantA->uuid,
            'original_name' => 'alpha-file.csv',
            'status' => 'done',
            'file_size_bytes' => 128,
            'total_rows' => 10,
        ]);

        LeadSource::query()->create([
            'tenant_uuid' => (string) $tenantB->uuid,
            'original_name' => 'beta-file.csv',
            'status' => 'queued',
            'file_size_bytes' => 256,
            'total_rows' => 20,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenantA->uuid,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.index'))
            ->assertStatus(200)
            ->assertSee('alpha-file.csv')
            ->assertSee('beta-file.csv');

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.index', ['tenant_uuid' => (string) $tenantA->uuid]))
            ->assertStatus(200)
            ->assertSee('alpha-file.csv')
            ->assertDontSee('beta-file.csv');

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.index'))
            ->assertStatus(200)
            ->assertSee('fileDetailModal')
            ->assertSee('fileHistoryModal')
            ->assertSee('Histórico de atualizações')
            ->assertSee('Arquivar')
            ->assertSee('Deletar')
            ->assertSee('data-file-detail="1"', false)
            ->assertDontSee('data-file-edit="1"', false)
            ->assertSee('data-file-history="1"', false);
    }

    public function test_legacy_customers_imports_route_returns_301_to_new_files_route(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Redirect',
            'plan' => 'free',
            'slug' => 'tenant-redirect-files',
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $response = $this->actingAs($superAdmin, 'web')
            ->get('/admin/customers/imports?status=done&q=arquivo')
            ->assertStatus(301);

        $location = (string) $response->headers->get('Location', '');
        $this->assertStringStartsWith(url('/admin/lists'), $location);
        $this->assertStringContainsString('status=done', $location);
        $this->assertStringContainsString('q=arquivo', $location);
    }

    public function test_legacy_customers_files_route_returns_301_to_new_files_route(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Redirect Files',
            'plan' => 'free',
            'slug' => 'tenant-redirect-files-legacy',
        ]);

        $source = LeadSource::query()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'original_name' => 'legacy.csv',
            'status' => 'done',
            'total_rows' => 1,
            'processed_rows' => 1,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $response = $this->actingAs($superAdmin, 'web')
            ->get('/admin/customers/files/' . $source->id . '/subscribers?tenant_uuid=' . $tenant->uuid . '&q=abc')
            ->assertStatus(301);

        $location = (string) $response->headers->get('Location', '');
        $this->assertStringStartsWith(url('/admin/lists/' . ($source->public_uid ?: $source->id) . '/subscribers'), $location);
        $this->assertStringContainsString('tenant_uuid=' . $tenant->uuid, $location);
        $this->assertStringContainsString('q=abc', $location);
    }

    public function test_legacy_admin_files_routes_return_301_to_lists_canonical(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Redirect Admin Files',
            'plan' => 'free',
            'slug' => 'tenant-redirect-admin-files',
        ]);

        $source = LeadSource::query()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'original_name' => 'legacy-admin-files.csv',
            'status' => 'done',
            'total_rows' => 1,
            'processed_rows' => 1,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $uid = (string) ($source->public_uid ?: $source->id);

        $response = $this->actingAs($superAdmin, 'web')
            ->get('/admin/files/' . $source->id . '?tenant_uuid=' . $tenant->uuid)
            ->assertStatus(301);

        $location = (string) $response->headers->get('Location', '');
        $this->assertStringStartsWith(url('/admin/lists/' . $uid . '/overview'), $location);
        $this->assertStringContainsString('tenant_uuid=' . $tenant->uuid, $location);

        $response2 = $this->actingAs($superAdmin, 'web')
            ->get('/admin/files/' . $source->id . '/subscribers?tenant_uuid=' . $tenant->uuid)
            ->assertStatus(301);

        $location2 = (string) $response2->headers->get('Location', '');
        $this->assertStringStartsWith(url('/admin/lists/' . $uid . '/subscribers'), $location2);
        $this->assertStringContainsString('tenant_uuid=' . $tenant->uuid, $location2);
    }

    public function test_superadmin_can_open_file_full_overview_page(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Full Overview',
            'plan' => 'free',
            'slug' => 'tenant-full-overview-files',
        ]);

        $source = LeadSource::query()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'original_name' => 'overview.csv',
            'display_name' => 'Arquivo Overview',
            'status' => 'done',
            'file_size_bytes' => 1024,
            'processed_rows' => 10,
            'total_rows' => 10,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.show', [
                'id' => $source->id,
                'tenant_uuid' => (string) $tenant->uuid,
            ]))
            ->assertStatus(200)
            ->assertSee('Detalhes')
            ->assertSee('Arquivo Overview')
            ->assertSee('Assinantes')
            ->assertSee('/admin/lists/' . ($source->public_uid ?: $source->id) . '/subscribers', false)
            ->assertSee('Semânticas')
            ->assertSee('Atualizações dos assinantes nos últimos 7 dias')
            ->assertSee('List growth')
            ->assertSee('Qualidade dos assinantes');
    }

    public function test_superadmin_can_open_subscribers_table_from_file_context(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Subscribers',
            'plan' => 'free',
            'slug' => 'tenant-subscribers-files',
        ]);

        $source = LeadSource::query()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'original_name' => 'subscribers.csv',
            'status' => 'done',
            'total_rows' => 2,
            'processed_rows' => 2,
        ]);

        DB::table('leads_normalized')->insert([
            [
                'tenant_uuid' => (string) $tenant->uuid,
                'lead_source_id' => (int) $source->id,
                'row_num' => 1,
                'public_uid' => 'mtestsub000001',
                'name' => 'Assinante Um',
                'email' => 'assinante1@example.com',
                'phone_e164' => '+5511999999991',
                'city' => 'Campinas',
                'uf' => 'SP',
                'score' => 85,
                'extras_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'tenant_uuid' => (string) $tenant->uuid,
                'lead_source_id' => (int) $source->id,
                'row_num' => 2,
                'public_uid' => 'mtestsub000002',
                'name' => 'Assinante Dois',
                'email' => 'assinante2@example.com',
                'phone_e164' => '+5511999999992',
                'city' => 'São Paulo',
                'uf' => 'SP',
                'score' => 55,
                'extras_json' => json_encode(['custom_field' => 'valor teste'], JSON_UNESCAPED_UNICODE),
                'created_at' => now()->subHours(10),
                'updated_at' => now()->subHours(10),
            ],
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.subscribers', [
                'id' => (string) ($source->public_uid ?: $source->id),
                'tenant_uuid' => (string) $tenant->uuid,
                'per_page' => 10,
            ]))
            ->assertStatus(200)
            ->assertSee('Lista de Assinantes')
            ->assertSee('sem impersonação de sessão')
            ->assertSee('Escopo travado por lista')
            ->assertSee('e cliente')
            ->assertSee('Filtros')
            ->assertSee('Colunas')
            ->assertSee('Selecionar colunas da tabela')
            ->assertSee('Exportar CSV')
            ->assertSee('WhatsApp')
            ->assertSee('Consentimento')
            ->assertSee('Perfil do assinante')
            ->assertSee('subscriberProfileModal', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('1000/pág')
            ->assertDontSee('<th style="width:72px">#</th>', false)
            ->assertSee('Assinante Um')
            ->assertSee('Assinante Dois')
            ->assertSee('Assinantes');

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.subscribers', [
                'id' => (string) ($source->public_uid ?: $source->id),
                'tenant_uuid' => (string) $tenant->uuid,
                'q' => 'Campinas',
                'score_min' => 80,
            ]))
            ->assertStatus(200)
            ->assertSee('Assinante Um')
            ->assertDontSee('Assinante Dois');

        $export = $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.subscribersExport', [
                'id' => (string) ($source->public_uid ?: $source->id),
                'tenant_uuid' => (string) $tenant->uuid,
                'q' => 'Campinas',
                'score_min' => 80,
            ]))
            ->assertStatus(200);

        $this->assertStringContainsString('text/csv', (string) $export->headers->get('Content-Type', ''));
        $this->assertStringContainsString('subscribers-file-' . $source->id, (string) $export->headers->get('Content-Disposition', ''));
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Assinante Um', $csv);
        $this->assertStringNotContainsString('Assinante Dois', $csv);

        $subscriber = DB::table('leads_normalized')
            ->where('lead_source_id', (int) $source->id)
            ->where('name', 'Assinante Dois')
            ->first(['id', 'public_uid']);
        $this->assertNotNull($subscriber);
        $subscriberId = (int) ($subscriber->id ?? 0);
        $subscriberRouteKey = (string) ($subscriber->public_uid ?: $subscriberId);

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.subscribers.edit', [
                'id' => (string) ($source->public_uid ?: $source->id),
                'subscriberId' => $subscriberRouteKey,
                'tenant_uuid' => (string) $tenant->uuid,
                'q' => 'Campinas',
                'score_min' => 80,
                'per_page' => 10,
                'page' => 2,
            ]))
            ->assertStatus(200)
            ->assertSee('Editar assinante')
            ->assertSee('Campos dinâmicos do arquivo')
            ->assertSee('custom_field');

        $this->actingAs($superAdmin, 'web')
            ->put(route('admin.customers.files.subscribers.update', [
                'id' => (string) ($source->public_uid ?: $source->id),
                'subscriberId' => $subscriberRouteKey,
            ]), [
                'tenant_uuid' => (string) $tenant->uuid,
                'name' => 'Assinante Dois Editado',
                'email' => 'assinante2.editado@example.com',
                'phone_e164' => '+5511988887777',
                'whatsapp_e164' => '+5511988886666',
                'city' => 'Santos',
                'uf' => 'SP',
                'entity_type' => 'person',
                'lifecycle_stage' => 'active',
                'score' => 91,
                'consent_source' => 'admin.manual',
                'optin_email' => '1',
                'optin_whatsapp' => '1',
                'extras' => [
                    'custom_field' => 'valor atualizado',
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHas('status', 'Assinante atualizado com sucesso.');

        $this->assertDatabaseHas('leads_normalized', [
            'id' => $subscriberId,
            'lead_source_id' => (int) $source->id,
            'tenant_uuid' => (string) $tenant->uuid,
            'name' => 'Assinante Dois Editado',
            'email' => 'assinante2.editado@example.com',
            'phone_e164' => '+5511988887777',
            'whatsapp_e164' => '+5511988886666',
            'city' => 'Santos',
            'uf' => 'SP',
            'entity_type' => 'person',
            'lifecycle_stage' => 'active',
            'score' => 91,
            'consent_source' => 'admin.manual',
            'optin_email' => 1,
            'optin_sms' => 0,
            'optin_whatsapp' => 1,
        ]);

        $updatedExtras = DB::table('leads_normalized')->where('id', $subscriberId)->value('extras_json');
        $this->assertIsString($updatedExtras);
        $this->assertStringContainsString('valor atualizado', (string) $updatedExtras);
    }

    public function test_superadmin_can_cancel_file_from_admin_customers_files(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Cancel',
            'plan' => 'free',
            'slug' => 'tenant-cancel-files',
        ]);

        $source = LeadSource::query()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'original_name' => 'cancel-me.csv',
            'status' => 'importing',
            'file_size_bytes' => 512,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin, 'web')
            ->post(route('admin.customers.files.cancel', ['id' => $source->id]), [
                'tenant_uuid' => (string) $tenant->uuid,
            ])
            ->assertStatus(302)
            ->assertSessionHas('status');

        $this->assertDatabaseHas('lead_sources', [
            'id' => (int) $source->id,
            'status' => 'cancelled',
            'cancel_requested' => 1,
        ]);
    }

    public function test_superadmin_can_reprocess_file_from_admin_customers_files(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Tenant Reprocess',
            'plan' => 'free',
            'slug' => 'tenant-reprocess-files',
        ]);

        $source = LeadSource::query()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'original_name' => 'reprocess-me.csv',
            'status' => 'failed',
            'progress_percent' => 85,
            'processed_rows' => 85,
            'inserted_rows' => 80,
            'skipped_rows' => 5,
            'total_rows' => 100,
            'cancel_requested' => true,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin, 'web')
            ->post(route('admin.customers.files.reprocess', ['id' => $source->id]), [
                'tenant_uuid' => (string) $tenant->uuid,
            ])
            ->assertStatus(302)
            ->assertSessionHas('status');

        $this->assertDatabaseHas('lead_sources', [
            'id' => (int) $source->id,
            'status' => 'queued',
            'progress_percent' => 0,
            'processed_rows' => 0,
            'inserted_rows' => 0,
            'skipped_rows' => 0,
            'cancel_requested' => 0,
        ]);

        Queue::assertPushed(ImportLeadSourceJob::class, function (ImportLeadSourceJob $job) use ($source): bool {
            return (int) $job->sourceId === (int) $source->id;
        });
    }

    public function test_superadmin_can_edit_archive_and_soft_delete_file_from_admin_customers_files(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Lifecycle',
            'plan' => 'free',
            'slug' => 'tenant-lifecycle-files',
        ]);

        $source = LeadSource::query()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'original_name' => 'lifecycle.csv',
            'status' => 'done',
            'file_size_bytes' => 1024,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin, 'web')
            ->put(route('admin.customers.files.update', ['id' => $source->id]), [
                'tenant_uuid' => (string) $tenant->uuid,
                'display_name' => 'Arquivo Comercial',
                'admin_tags' => 'vip, fevereiro',
                'admin_notes' => 'Notas internas',
            ])
            ->assertStatus(302)
            ->assertSessionHas('status');

        $this->assertDatabaseHas('lead_sources', [
            'id' => (int) $source->id,
            'display_name' => 'Arquivo Comercial',
            'admin_notes' => 'Notas internas',
        ]);

        $this->actingAs($superAdmin, 'web')
            ->post(route('admin.customers.files.archive', ['id' => $source->id]), [
                'tenant_uuid' => (string) $tenant->uuid,
            ])
            ->assertStatus(302)
            ->assertSessionHas('status');

        $this->assertNotNull(LeadSource::withoutGlobalScopes()->findOrFail((int) $source->id)->archived_at);

        $this->actingAs($superAdmin, 'web')
            ->delete(route('admin.customers.files.destroy', ['id' => $source->id]), [
                'tenant_uuid' => (string) $tenant->uuid,
            ])
            ->assertStatus(302)
            ->assertSessionHas('status');

        $this->assertNotNull(LeadSource::withoutGlobalScopes()->findOrFail((int) $source->id)->deleted_at);
    }

    public function test_superadmin_can_execute_bulk_action_on_selected_subscribers(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Bulk',
            'plan' => 'free',
            'slug' => 'tenant-bulk-files',
        ]);

        $source = LeadSource::query()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'original_name' => 'bulk.csv',
            'status' => 'done',
            'total_rows' => 2,
            'processed_rows' => 2,
        ]);

        DB::table('leads_normalized')->insert([
            [
                'tenant_uuid' => (string) $tenant->uuid,
                'lead_source_id' => (int) $source->id,
                'row_num' => 1,
                'public_uid' => 'mbulk000000001',
                'name' => 'Bulk Um',
                'email' => 'bulk1@example.com',
                'optin_email' => 0,
                'lifecycle_stage' => 'new',
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ],
            [
                'tenant_uuid' => (string) $tenant->uuid,
                'lead_source_id' => (int) $source->id,
                'row_num' => 2,
                'public_uid' => 'mbulk000000002',
                'name' => 'Bulk Dois',
                'email' => 'bulk2@example.com',
                'optin_email' => 0,
                'lifecycle_stage' => 'new',
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(30),
            ],
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $bulkDeactivate = $this->actingAs($superAdmin, 'web')
            ->postJson(route('admin.customers.files.subscribers.bulkAction', [
                'id' => (string) ($source->public_uid ?: $source->id),
            ]), [
                'tenant_uuid' => (string) $tenant->uuid,
                'action' => 'deactivate',
                'scope' => 'selected',
                'subscriber_ids' => ['mbulk000000001', 'mbulk000000002'],
            ]);

        $bulkDeactivate
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'action' => 'deactivate'])
            ->assertJsonPath('targeted', 2)
            ->assertJsonPath('affected', 2);

        $this->assertDatabaseHas('leads_normalized', [
            'lead_source_id' => (int) $source->id,
            'public_uid' => 'mbulk000000001',
            'lifecycle_stage' => 'inactive',
        ]);
        $this->assertDatabaseHas('leads_normalized', [
            'lead_source_id' => (int) $source->id,
            'public_uid' => 'mbulk000000002',
            'lifecycle_stage' => 'inactive',
        ]);

        $bulkDelete = $this->actingAs($superAdmin, 'web')
            ->postJson(route('admin.customers.files.subscribers.bulkAction', [
                'id' => (string) ($source->public_uid ?: $source->id),
            ]), [
                'tenant_uuid' => (string) $tenant->uuid,
                'action' => 'delete',
                'scope' => 'selected',
                'subscriber_ids' => ['mbulk000000001'],
            ]);

        $bulkDelete
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'action' => 'delete'])
            ->assertJsonPath('targeted', 1);

        $this->assertDatabaseMissing('leads_normalized', [
            'lead_source_id' => (int) $source->id,
            'public_uid' => 'mbulk000000001',
        ]);
        $this->assertDatabaseHas('leads_normalized', [
            'lead_source_id' => (int) $source->id,
            'public_uid' => 'mbulk000000002',
        ]);
    }

    public function test_superadmin_can_open_history_and_download_error_report_and_retry_failed_rows(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Tenant History',
            'plan' => 'free',
            'slug' => 'tenant-history-files',
        ]);

        $source = LeadSource::query()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'original_name' => 'history.csv',
            'status' => 'failed',
            'last_error' => 'Header inválido',
            'file_size_bytes' => 128,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        DB::table('guest_file_events')->insert([
            [
                'guest_uuid' => '00000000-0000-0000-0000-000000000001',
                'actor_type' => 'user',
                'user_id' => (int) $superAdmin->id,
                'tenant_uuid' => (string) $tenant->uuid,
                'lead_source_id' => (int) $source->id,
                'action' => 'edit',
                'file_name' => 'history.csv',
                'payload_json' => json_encode([
                    'diff' => [
                        'display_name' => ['from' => null, 'to' => 'Arquivo Comercial'],
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now()->subMinutes(10),
            ],
            [
                'guest_uuid' => '00000000-0000-0000-0000-000000000001',
                'actor_type' => 'user',
                'user_id' => (int) $superAdmin->id,
                'tenant_uuid' => (string) $tenant->uuid,
                'lead_source_id' => (int) $source->id,
                'action' => 'reprocess',
                'file_name' => 'history.csv',
                'payload_json' => json_encode([
                    'mvp_mode' => 'full_reprocess',
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now()->subMinutes(5),
            ],
        ]);

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.history', [
                'id' => $source->id,
                'tenant_uuid' => (string) $tenant->uuid,
                'action' => 'edit',
                'sort' => 'desc',
                'page' => 1,
                'per_page' => 10,
            ]))
            ->assertStatus(200)
            ->assertJson(['ok' => true])
            ->assertJsonPath('events.0.action', 'edit')
            ->assertJsonPath('events.0.actor_name', (string) $superAdmin->name)
            ->assertJsonPath('pagination.page', 1)
            ->assertJsonPath('filters.action', 'edit')
            ->assertJsonPath('filters.sort', 'desc');

        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.history', [
                'id' => $source->id,
                'tenant_uuid' => (string) $tenant->uuid,
                'sort' => 'asc',
                'page' => 1,
                'per_page' => 10,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('events.0.action', 'edit')
            ->assertJsonPath('filters.sort', 'asc');

        $this->actingAs($superAdmin, 'web')
            ->post(route('admin.customers.files.retryFailedRows', ['id' => $source->id]), [
                'tenant_uuid' => (string) $tenant->uuid,
            ])
            ->assertStatus(302)
            ->assertSessionHas('status');

        Queue::assertPushed(ImportLeadSourceJob::class);

        $download = $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.errorReport', ['id' => $source->id, 'tenant_uuid' => (string) $tenant->uuid]));

        $download->assertStatus(200);
        $download->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $historyExport = $this->actingAs($superAdmin, 'web')
            ->get(route('admin.customers.files.historyExport', [
                'id' => $source->id,
                'tenant_uuid' => (string) $tenant->uuid,
                'action' => 'edit',
                'sort' => 'asc',
            ]));

        $historyExport->assertStatus(200);
        $historyExport->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
