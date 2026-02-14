<?php

namespace Tests\Feature\Admin;

use App\Models\PricePlan;
use App\Models\Tenant;
use App\Models\TenantUserGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanAdminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_admin_plans_path_redirects_permanently_to_customers_plans(): void
    {
        $superadmin = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($superadmin, 'web')
            ->get('/admin/plans')
            ->assertStatus(301)
            ->assertHeader('Location', url('/admin/customers/subscriptions'));
    }

    public function test_plan_update_reprovisions_customer_groups_by_plan(): void
    {
        $superadmin = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Tenant Plan Target',
            'slug' => 'tenant-plan-target',
            'plan' => 'free',
        ]);

        TenantUserGroup::query()->create([
            'tenant_id' => (int) $tenant->id,
            'tenant_uuid' => (string) $tenant->uuid,
            'slug' => 'manager',
            'name' => 'Manager',
            'description' => 'Old manager policy',
            'is_active' => true,
            'permissions_json' => ['inbox.view'],
        ]);

        $pricePlanPro = PricePlan::query()->create([
            'code' => 'pro',
            'name' => 'Pro Mensal',
            'billing_interval' => 'monthly',
            'amount_minor' => 9990,
            'currency_code' => 'BRL',
            'is_active' => true,
        ]);

        $this->actingAs($superadmin, 'web')
            ->post(route('admin.plans.update', $tenant->id), [
                'price_plan_id' => (int) $pricePlanPro->id,
            ])
            ->assertStatus(302);

        $tenant->refresh();
        $this->assertSame('pro', (string) $tenant->plan);

        $manager = TenantUserGroup::query()
            ->where('tenant_id', (int) $tenant->id)
            ->where('slug', 'manager')
            ->firstOrFail();

        $permissions = collect($manager->permissions_json ?? [])->map(fn ($p) => (string) $p)->values()->all();
        $this->assertContains('campaigns.manage', $permissions);
        $this->assertContains('exports.manage', $permissions);
        $this->assertContains('inbox.manage', $permissions);
    }

    public function test_customers_plans_uses_monetization_catalog_as_source(): void
    {
        $superadmin = User::factory()->create([
            'is_super_admin' => true,
        ]);

        Tenant::query()->create([
            'name' => 'Tenant Catalog Check',
            'slug' => 'tenant-catalog-check',
            'plan' => 'free',
        ]);

        PricePlan::query()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'billing_interval' => 'monthly',
            'amount_minor' => 199900,
            'currency_code' => 'BRL',
            'is_active' => true,
        ]);

        $this->actingAs($superadmin, 'web')
            ->get(route('admin.customers.subscriptions.index'))
            ->assertStatus(200)
            ->assertSee('Enterprise');
    }
}
