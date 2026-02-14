<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TenantLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TenantAuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.tenant-login');
    }

    public function store(TenantLoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        $request->session()->regenerate();
        $request->session()->put('tenant_uuid', (string) $user->tenant_uuid);

        app()->instance('tenant_uuid', (string) $user->tenant_uuid);

        return redirect()->intended(route('home', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('tenant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
