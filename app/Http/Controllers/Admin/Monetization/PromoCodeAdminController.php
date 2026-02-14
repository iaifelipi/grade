<?php

namespace App\Http\Controllers\Admin\Monetization;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PromoCodeAdminController extends Controller
{
    public function index(): View
    {
        $items = PromoCode::query()->orderByDesc('id')->get();
        return view('admin.monetization.promo-codes', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:monetization_promo_codes,code'],
            'name' => ['nullable', 'string', 'max:120'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        PromoCode::query()->create([
            'code' => strtoupper((string) $data['code']),
            'name' => $data['name'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'currency_code' => !empty($data['currency_code']) ? strtoupper((string) $data['currency_code']) : null,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', 'Cupom criado.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = PromoCode::query()->findOrFail($id);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('monetization_promo_codes', 'code')->ignore($item->id)],
            'name' => ['nullable', 'string', 'max:120'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $item->update([
            'code' => strtoupper((string) $data['code']),
            'name' => $data['name'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'currency_code' => !empty($data['currency_code']) ? strtoupper((string) $data['currency_code']) : null,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Cupom atualizado.');
    }

    public function destroy(int $id): RedirectResponse
    {
        PromoCode::query()->findOrFail($id)->delete();
        return back()->with('status', 'Cupom removido.');
    }
}
