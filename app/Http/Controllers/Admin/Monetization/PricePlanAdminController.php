<?php

namespace App\Http\Controllers\Admin\Monetization;

use App\Http\Controllers\Controller;
use App\Models\PricePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PricePlanAdminController extends Controller
{
    public function index(): View
    {
        $items = PricePlan::query()->orderBy('name')->get();
        return view('admin.monetization.price-plans', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:monetization_price_plans,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly', 'one_time'])],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        $resolvedCode = strtolower((string) $data['code']);

        PricePlan::query()->create([
            'code' => $resolvedCode,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'billing_interval' => $data['billing_interval'],
            'amount_minor' => $data['amount_minor'],
            'currency_code' => strtoupper((string) $data['currency_code']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', 'Plano de preço criado.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = PricePlan::query()->findOrFail($id);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('monetization_price_plans', 'code')->ignore($item->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly', 'one_time'])],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        $resolvedCode = strtolower((string) $data['code']);

        $item->update([
            'code' => $resolvedCode,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'billing_interval' => $data['billing_interval'],
            'amount_minor' => $data['amount_minor'],
            'currency_code' => strtoupper((string) $data['currency_code']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Plano de preço atualizado.');
    }

    public function destroy(int $id): RedirectResponse
    {
        PricePlan::query()->findOrFail($id)->delete();
        return back()->with('status', 'Plano de preço removido.');
    }
}
