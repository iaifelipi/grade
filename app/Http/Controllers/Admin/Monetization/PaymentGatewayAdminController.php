<?php

namespace App\Http\Controllers\Admin\Monetization;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentGatewayAdminController extends Controller
{
    public function index(): View
    {
        $items = PaymentGateway::query()->orderBy('name')->get();
        return view('admin.monetization.gateways', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:monetization_payment_gateways,code'],
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', 'string', 'max:80'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_fixed_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        PaymentGateway::query()->create([
            'code' => strtolower((string) $data['code']),
            'name' => $data['name'],
            'provider' => $data['provider'],
            'fee_percent' => $data['fee_percent'] ?? 0,
            'fee_fixed_minor' => $data['fee_fixed_minor'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', 'Gateway criado.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = PaymentGateway::query()->findOrFail($id);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('monetization_payment_gateways', 'code')->ignore($item->id)],
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', 'string', 'max:80'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_fixed_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $item->update([
            'code' => strtolower((string) $data['code']),
            'name' => $data['name'],
            'provider' => $data['provider'],
            'fee_percent' => $data['fee_percent'] ?? 0,
            'fee_fixed_minor' => $data['fee_fixed_minor'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Gateway atualizado.');
    }

    public function destroy(int $id): RedirectResponse
    {
        PaymentGateway::query()->findOrFail($id)->delete();
        return back()->with('status', 'Gateway removido.');
    }
}
