<?php

namespace App\Http\Controllers\Admin\Monetization;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxRateAdminController extends Controller
{
    public function index(): View
    {
        $items = TaxRate::query()->orderBy('name')->get();
        return view('admin.monetization.taxes', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'state_code' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:120'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        TaxRate::query()->create([
            'name' => $data['name'],
            'country_code' => isset($data['country_code']) ? strtoupper((string) $data['country_code']) : null,
            'state_code' => $data['state_code'] ?? null,
            'city' => $data['city'] ?? null,
            'rate_percent' => $data['rate_percent'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', 'Imposto criado.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = TaxRate::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'state_code' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:120'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $item->update([
            'name' => $data['name'],
            'country_code' => isset($data['country_code']) ? strtoupper((string) $data['country_code']) : null,
            'state_code' => $data['state_code'] ?? null,
            'city' => $data['city'] ?? null,
            'rate_percent' => $data['rate_percent'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Imposto atualizado.');
    }

    public function destroy(int $id): RedirectResponse
    {
        TaxRate::query()->findOrFail($id)->delete();
        return back()->with('status', 'Imposto removido.');
    }
}
