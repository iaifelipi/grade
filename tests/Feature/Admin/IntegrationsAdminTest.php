<?php

namespace Tests\Feature\Admin;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_integrations_screen(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/admin/integrations');

        $response->assertStatus(200);
    }

    public function test_super_admin_can_create_integration(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/admin/integrations', [
                'provider' => 'mailwizz',
                'key' => 'mailwizz-main',
                'name' => 'Mailwizz Principal',
                'status' => 'active',
                'secrets' => [
                    'base_url' => 'https://mail.example.test',
                    'api_key' => 'api_123',
                ],
                'settings' => [
                    'timeout' => 10,
                ],
            ]);

        $response->assertRedirect();

        $integration = Integration::query()->first();
        $this->assertNotNull($integration);
        $this->assertSame('global', $integration->tenant_uuid);
        $this->assertSame('mailwizz', $integration->provider);
        $this->assertSame('mailwizz-main', $integration->key);
        $this->assertSame('Mailwizz Principal', $integration->name);

        $secrets = (array) ($integration->secrets_enc ?? []);
        $this->assertSame('https://mail.example.test', $secrets['base_url'] ?? null);
        $this->assertSame('api_123', $secrets['api_key'] ?? null);
    }
}
