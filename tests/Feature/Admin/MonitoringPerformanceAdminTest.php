<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringPerformanceAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_open_performance_dashboard(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Perf',
            'plan' => 'free',
            'slug' => 'tenant-perf',
        ]);

        $superAdmin = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => true,
        ]);

        $response = $this->actingAs($superAdmin, 'web')
            ->get(route('admin.monitoring.performance'));

        $response->assertStatus(200)
            ->assertSee('Performance Técnico')
            ->assertSee('Rotas mais lentas')
            ->assertSee('Últimos eventos lentos');
    }
}
