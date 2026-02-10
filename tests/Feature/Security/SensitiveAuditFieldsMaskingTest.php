<?php

namespace Tests\Feature\Security;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SensitiveAuditFieldsMaskingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/__test/sensitive-audit-json', function () {
            return response()->json([
                'ok' => true,
                'ip_raw' => '192.168.0.10',
                'ip_enc' => 'encrypted-value',
                'ua_raw' => 'UA test',
                'nested' => [
                    'ip_raw' => '10.0.0.1',
                    'safe' => 'value',
                ],
            ]);
        });
    }

    public function test_guest_response_masks_sensitive_fields(): void
    {
        $response = $this->getJson('/__test/sensitive-audit-json');
        $response->assertOk();

        $response->assertJsonPath('ip_raw', null);
        $response->assertJsonPath('ip_enc', null);
        $response->assertJsonPath('ua_raw', null);
        $response->assertJsonPath('nested.ip_raw', null);
    }

    public function test_authenticated_user_without_permission_gets_masked_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/__test/sensitive-audit-json');
        $response->assertOk();

        $response->assertJsonPath('ip_raw', null);
        $response->assertJsonPath('ip_enc', null);
        $response->assertJsonPath('ua_raw', null);
        $response->assertJsonPath('nested.ip_raw', null);
    }

    public function test_user_with_audit_permission_sees_sensitive_fields_and_logs_access(): void
    {
        $user = User::factory()->create();
        $this->grantSensitiveAuditPermission($user);

        $response = $this->actingAs($user)->getJson('/__test/sensitive-audit-json');
        $response->assertOk();

        $response->assertJsonPath('ip_raw', '192.168.0.10');
        $response->assertJsonPath('ip_enc', 'encrypted-value');
        $response->assertJsonPath('ua_raw', 'UA test');
        $response->assertJsonPath('nested.ip_raw', '10.0.0.1');

        $this->assertDatabaseHas('audit_sensitive_access_logs', [
            'user_id' => $user->id,
            'request_path' => '__test/sensitive-audit-json',
            'permission_name' => 'audit.view_sensitive',
        ]);
    }

    public function test_super_admin_sees_sensitive_fields_and_logs_access(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/__test/sensitive-audit-json');
        $response->assertOk();

        $response->assertJsonPath('ip_raw', '192.168.0.10');
        $response->assertJsonPath('ip_enc', 'encrypted-value');
        $response->assertJsonPath('ua_raw', 'UA test');

        $this->assertDatabaseHas('audit_sensitive_access_logs', [
            'user_id' => $user->id,
            'request_path' => '__test/sensitive-audit-json',
            'permission_name' => 'audit.view_sensitive',
        ]);
    }

    private function grantSensitiveAuditPermission(User $user): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'audit.view_sensitive',
            'guard_name' => 'web',
        ]);

        $role = Role::firstOrCreate([
            'name' => 'audit-reader',
            'guard_name' => 'web',
            'tenant_uuid' => $user->tenant_uuid,
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}

