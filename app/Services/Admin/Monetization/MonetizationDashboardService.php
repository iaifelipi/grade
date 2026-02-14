<?php

namespace App\Services\Admin\Monetization;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PricePlan;
use App\Models\PromoCode;
use App\Models\Currency;
use App\Models\TaxRate;

class MonetizationDashboardService
{
    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $ordersQuery = Order::query();

        return [
            'kpis' => [
                'gateways' => PaymentGateway::query()->count(),
                'price_plans' => PricePlan::query()->count(),
                'orders' => $ordersQuery->count(),
                'promo_codes' => PromoCode::query()->count(),
                'currencies' => Currency::query()->count(),
                'tax_rates' => TaxRate::query()->count(),
                'paid_revenue_minor' => (int) Order::query()
                    ->whereIn('payment_status', ['paid', 'captured'])
                    ->sum('total_minor'),
            ],
            'recent_orders' => Order::query()
                ->with(['gateway:id,name', 'pricePlan:id,name'])
                ->latest('id')
                ->limit(8)
                ->get(),
        ];
    }
}
