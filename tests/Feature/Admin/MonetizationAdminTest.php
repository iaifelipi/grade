<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetizationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_monetization_pages(): void
    {
        $manager = $this->seedManager();

        $this->actingAs($manager, 'web')
            ->get(route('admin.monetization.dashboard'))
            ->assertStatus(200)
            ->assertSee('Cobranças');

        $this->actingAs($manager, 'web')->get(route('admin.monetization.gateways.index'))->assertStatus(200);
        $this->actingAs($manager, 'web')->get(route('admin.monetization.price-plans.index'))->assertStatus(200);
        $this->actingAs($manager, 'web')->get(route('admin.monetization.orders.index'))->assertStatus(200);
        $this->actingAs($manager, 'web')->get(route('admin.monetization.promo-codes.index'))->assertStatus(200);
        $this->actingAs($manager, 'web')->get(route('admin.monetization.currencies.index'))->assertStatus(200);
        $this->actingAs($manager, 'web')->get(route('admin.monetization.taxes.index'))->assertStatus(200);
    }

    public function test_admin_can_crud_monetization_entities(): void
    {
        $manager = $this->seedManager();

        $this->actingAs($manager, 'web')
            ->post(route('admin.monetization.gateways.store'), [
                'code' => 'stripe',
                'name' => 'Stripe',
                'provider' => 'stripe',
                'fee_percent' => '3.50',
                'fee_fixed_minor' => 39,
                'is_active' => 1,
            ])
            ->assertStatus(302);

        $this->actingAs($manager, 'web')
            ->post(route('admin.monetization.currencies.store'), [
                'code' => 'BRL',
                'name' => 'Real',
                'symbol' => 'R$',
                'decimal_places' => 2,
                'is_active' => 1,
                'is_default' => 1,
            ])
            ->assertStatus(302);

        $this->actingAs($manager, 'web')
            ->post(route('admin.monetization.taxes.store'), [
                'name' => 'ICMS',
                'country_code' => 'BR',
                'state_code' => 'SP',
                'city' => 'Sao Paulo',
                'rate_percent' => '17.5000',
                'is_active' => 1,
            ])
            ->assertStatus(302);

        $this->actingAs($manager, 'web')
            ->post(route('admin.monetization.price-plans.store'), [
                'code' => 'pro_monthly',
                'name' => 'Pro Mensal',
                'billing_interval' => 'monthly',
                'amount_minor' => 9990,
                'currency_code' => 'BRL',
                'is_active' => 1,
            ])
            ->assertStatus(302);

        $this->actingAs($manager, 'web')
            ->post(route('admin.monetization.promo-codes.store'), [
                'code' => 'WELCOME10',
                'discount_type' => 'percent',
                'discount_value' => '10.00',
                'is_active' => 1,
            ])
            ->assertStatus(302);

        $gatewayId = (int) \App\Models\PaymentGateway::query()->value('id');
        $pricePlanId = (int) \App\Models\PricePlan::query()->value('id');
        $promoCodeId = (int) \App\Models\PromoCode::query()->value('id');
        $taxRateId = (int) \App\Models\TaxRate::query()->value('id');

        $this->actingAs($manager, 'web')
            ->post(route('admin.monetization.orders.store'), [
                'tenant_uuid' => (string) $manager->tenant_uuid,
                'user_id' => (int) $manager->id,
                'gateway_id' => $gatewayId,
                'price_plan_id' => $pricePlanId,
                'promo_code_id' => $promoCodeId,
                'tax_rate_id' => $taxRateId,
                'currency_code' => 'BRL',
                'subtotal_minor' => 10000,
                'discount_minor' => 1000,
                'tax_minor' => 500,
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ])
            ->assertStatus(302);

        $orderId = (int) \App\Models\Order::query()->value('id');

        $this->actingAs($manager, 'web')
            ->put(route('admin.monetization.orders.update', $orderId), [
                'status' => 'completed',
                'payment_status' => 'paid',
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('monetization_payment_gateways', ['code' => 'stripe']);
        $this->assertDatabaseHas('monetization_currencies', ['code' => 'BRL']);
        $this->assertDatabaseHas('monetization_tax_rates', ['name' => 'ICMS']);
        $this->assertDatabaseHas('monetization_price_plans', ['code' => 'pro_monthly']);
        $this->assertDatabaseHas('monetization_promo_codes', ['code' => 'WELCOME10']);
        $this->assertDatabaseHas('monetization_orders', [
            'id' => $orderId,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
    }

    private function seedManager(): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Monetization',
            'plan' => 'free',
            'slug' => 'tenant-monetization',
        ]);

        Permission::query()->firstOrCreate([
            'name' => 'users.manage',
            'guard_name' => 'web',
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'admin',
            'tenant_uuid' => (string) $tenant->uuid,
            'guard_name' => 'web',
        ]);
        $role->syncPermissionsByName(['users.manage']);

        $user = User::factory()->create([
            'tenant_uuid' => (string) $tenant->uuid,
            'is_super_admin' => false,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
