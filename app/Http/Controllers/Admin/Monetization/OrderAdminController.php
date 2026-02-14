<?php

namespace App\Http\Controllers\Admin\Monetization;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PricePlan;
use App\Models\PromoCode;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderAdminController extends Controller
{
    public function index(): View
    {
        $items = Order::query()
            ->with(['gateway:id,name', 'pricePlan:id,name', 'promoCode:id,code', 'taxRate:id,name', 'user:id,name,email'])
            ->latest('id')
            ->limit(200)
            ->get();

        return view('admin.monetization.orders', [
            'items' => $items,
            'gateways' => PaymentGateway::query()->orderBy('name')->get(['id', 'name']),
            'pricePlans' => PricePlan::query()->orderBy('name')->get(['id', 'name']),
            'promoCodes' => PromoCode::query()->orderBy('code')->get(['id', 'code']),
            'taxRates' => TaxRate::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->limit(200)->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_uuid' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'gateway_id' => ['nullable', 'integer', 'exists:monetization_payment_gateways,id'],
            'price_plan_id' => ['nullable', 'integer', 'exists:monetization_price_plans,id'],
            'promo_code_id' => ['nullable', 'integer', 'exists:monetization_promo_codes,id'],
            'tax_rate_id' => ['nullable', 'integer', 'exists:monetization_tax_rates,id'],
            'currency_code' => ['required', 'string', 'size:3'],
            'subtotal_minor' => ['required', 'integer', 'min:0'],
            'discount_minor' => ['nullable', 'integer', 'min:0'],
            'tax_minor' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['pending', 'processing', 'completed', 'cancelled'])],
            'payment_status' => ['required', Rule::in(['unpaid', 'paid', 'failed', 'refunded', 'captured'])],
        ]);

        $subtotal = (int) $data['subtotal_minor'];
        $discount = (int) ($data['discount_minor'] ?? 0);
        $tax = (int) ($data['tax_minor'] ?? 0);

        Order::query()->create([
            'order_number' => 'ORD-' . strtoupper(Str::random(10)),
            'tenant_uuid' => $data['tenant_uuid'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'gateway_id' => $data['gateway_id'] ?? null,
            'price_plan_id' => $data['price_plan_id'] ?? null,
            'promo_code_id' => $data['promo_code_id'] ?? null,
            'tax_rate_id' => $data['tax_rate_id'] ?? null,
            'currency_code' => strtoupper((string) $data['currency_code']),
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'tax_minor' => $tax,
            'total_minor' => max(0, $subtotal - $discount + $tax),
            'status' => $data['status'],
            'payment_status' => $data['payment_status'],
            'paid_at' => in_array($data['payment_status'], ['paid', 'captured'], true) ? now() : null,
        ]);

        return back()->with('status', 'Pedido criado.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = Order::query()->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'processing', 'completed', 'cancelled'])],
            'payment_status' => ['required', Rule::in(['unpaid', 'paid', 'failed', 'refunded', 'captured'])],
        ]);

        $item->update([
            'status' => $data['status'],
            'payment_status' => $data['payment_status'],
            'paid_at' => in_array($data['payment_status'], ['paid', 'captured'], true)
                ? ($item->paid_at ?: now())
                : null,
        ]);

        return back()->with('status', 'Pedido atualizado.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Order::query()->findOrFail($id)->delete();
        return back()->with('status', 'Pedido removido.');
    }
}
