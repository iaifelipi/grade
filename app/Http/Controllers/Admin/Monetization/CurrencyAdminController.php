<?php

namespace App\Http\Controllers\Admin\Monetization;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyAdminController extends Controller
{
    public function index(): View
    {
        $items = Currency::query()->orderBy('code')->get();
        return view('admin.monetization.currencies', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'alpha', 'unique:monetization_currencies,code'],
            'name' => ['required', 'string', 'max:80'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
        ]);

        if ($request->boolean('is_default')) {
            Currency::query()->update(['is_default' => false]);
        }

        Currency::query()->create([
            'code' => strtoupper((string) $data['code']),
            'name' => $data['name'],
            'symbol' => $data['symbol'] ?? null,
            'decimal_places' => $data['decimal_places'] ?? 2,
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default'),
        ]);

        return back()->with('status', 'Moeda criada.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = Currency::query()->findOrFail($id);

        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'alpha', Rule::unique('monetization_currencies', 'code')->ignore($item->id)],
            'name' => ['required', 'string', 'max:80'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
        ]);

        if ($request->boolean('is_default')) {
            Currency::query()->where('id', '!=', $item->id)->update(['is_default' => false]);
        }

        $item->update([
            'code' => strtoupper((string) $data['code']),
            'name' => $data['name'],
            'symbol' => $data['symbol'] ?? null,
            'decimal_places' => $data['decimal_places'] ?? 2,
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
        ]);

        return back()->with('status', 'Moeda atualizada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Currency::query()->findOrFail($id)->delete();
        return back()->with('status', 'Moeda removida.');
    }
}
