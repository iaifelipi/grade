<?php

namespace Tests\Feature\Guest;

use App\Services\GuestIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestAuditFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_creates_or_updates_guest_session(): void
    {
        $guestUuid = (string) Str::uuid();

        $this->withSession([
            'guest_tenant_uuid' => $guestUuid,
            'tenant_uuid' => $guestUuid,
        ])->get('/')
            ->assertStatus(200)
            ->assertCookie(GuestIdentityService::COOKIE_NAME);

        $this->assertDatabaseHas('guest_sessions', [
            'guest_uuid' => $guestUuid,
            'tenant_uuid' => $guestUuid,
        ]);
    }

    public function test_guest_upload_logs_upload_event(): void
    {
        $guestUuid = (string) Str::uuid();
        Storage::fake('private');
        Queue::fake();

        $file = UploadedFile::fake()->createWithContent('leads.csv', "nome,email\nA,a@x.com\n");

        $response = $this->withSession([
            'guest_tenant_uuid' => $guestUuid,
            'tenant_uuid' => $guestUuid,
        ])->post('/sources', [
            'files' => [$file],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $sourceId = (int) DB::table('lead_sources')
            ->where('tenant_uuid', $guestUuid)
            ->value('id');

        $this->assertGreaterThan(0, $sourceId);
        $this->assertDatabaseHas('guest_file_events', [
            'guest_uuid' => $guestUuid,
            'lead_source_id' => $sourceId,
            'action' => 'upload',
        ]);
    }

    public function test_guest_select_source_logs_source_select_event(): void
    {
        $guestUuid = (string) Str::uuid();
        $now = now();

        $sourceId = (int) DB::table('lead_sources')->insertGetId([
            'tenant_uuid' => $guestUuid,
            'original_name' => 'guest.csv',
            'file_path' => $guestUuid . '/imports/guest.csv',
            'file_ext' => 'csv',
            'file_size_bytes' => 123,
            'file_hash' => sha1('guest.csv'),
            'status' => 'done',
            'mapping_json' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->withSession([
            'guest_tenant_uuid' => $guestUuid,
            'tenant_uuid' => $guestUuid,
        ])->get('/explore/source/' . $sourceId)
            ->assertRedirect('/');

        $this->assertDatabaseHas('guest_file_events', [
            'guest_uuid' => $guestUuid,
            'lead_source_id' => $sourceId,
            'action' => 'source_select',
        ]);
    }

    public function test_guest_purge_selected_logs_delete_event_without_fk_break(): void
    {
        $guestUuid = (string) Str::uuid();
        $now = now();

        $sourceId = (int) DB::table('lead_sources')->insertGetId([
            'tenant_uuid' => $guestUuid,
            'original_name' => 'to-delete.csv',
            'file_path' => $guestUuid . '/imports/to-delete.csv',
            'file_ext' => 'csv',
            'file_size_bytes' => 999,
            'file_hash' => sha1('to-delete.csv'),
            'status' => 'done',
            'mapping_json' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->withSession([
            'guest_tenant_uuid' => $guestUuid,
            'tenant_uuid' => $guestUuid,
        ])->post('/sources/purge-selected', [
            'ids' => [$sourceId],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('lead_sources', ['id' => $sourceId]);

        $event = DB::table('guest_file_events')
            ->where('guest_uuid', $guestUuid)
            ->where('action', 'delete')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertNull($event->lead_source_id);
        $payload = json_decode((string) $event->payload_json, true) ?: [];
        $this->assertSame($sourceId, (int) ($payload['deleted_lead_source_id'] ?? 0));
    }

    public function test_guest_prune_command_removes_old_sessions_and_events(): void
    {
        $oldGuest = (string) Str::uuid();
        $newGuest = (string) Str::uuid();
        $now = now();

        DB::table('guest_sessions')->insert([
            [
                'guest_uuid' => $oldGuest,
                'tenant_uuid' => $oldGuest,
                'session_id' => 'old_session',
                'first_seen_at' => $now->copy()->subDays(120),
                'last_seen_at' => $now->copy()->subDays(120),
                'created_at' => $now->copy()->subDays(120),
                'updated_at' => $now->copy()->subDays(120),
            ],
            [
                'guest_uuid' => $newGuest,
                'tenant_uuid' => $newGuest,
                'session_id' => 'new_session',
                'first_seen_at' => $now->copy()->subDays(1),
                'last_seen_at' => $now->copy()->subDays(1),
                'created_at' => $now->copy()->subDays(1),
                'updated_at' => $now->copy()->subDays(1),
            ],
        ]);

        DB::table('guest_file_events')->insert([
            [
                'guest_uuid' => $oldGuest,
                'tenant_uuid' => $oldGuest,
                'session_id' => 'old_session',
                'action' => 'upload',
                'file_name' => 'old.csv',
                'created_at' => $now->copy()->subDays(120),
            ],
            [
                'guest_uuid' => $newGuest,
                'tenant_uuid' => $newGuest,
                'session_id' => 'new_session',
                'action' => 'upload',
                'file_name' => 'new.csv',
                'created_at' => $now->copy()->subDays(1),
            ],
        ]);

        $this->artisan('guest:prune-audit --sessions-days=30 --events-days=90')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('guest_file_events', [
            'guest_uuid' => $oldGuest,
            'file_name' => 'old.csv',
        ]);
        $this->assertDatabaseHas('guest_file_events', [
            'guest_uuid' => $newGuest,
            'file_name' => 'new.csv',
        ]);

        $this->assertDatabaseMissing('guest_sessions', [
            'guest_uuid' => $oldGuest,
        ]);
        $this->assertDatabaseHas('guest_sessions', [
            'guest_uuid' => $newGuest,
        ]);
    }
}

