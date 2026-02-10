<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Services\GuestIdentityService;
use App\Services\GuestTenantTransferService;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, GuestTenantTransferService $transferService): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $clearGuestCookie = false;

        $guestUuid = (string) $request->session()->get('guest_tenant_uuid', '');
        $userTenantUuid = (string) (auth()->user()->tenant_uuid ?? '');
        if ($guestUuid && $userTenantUuid && $guestUuid !== $userTenantUuid) {
            $transferService->transfer($guestUuid, $userTenantUuid, auth()->id());
            $request->session()->forget('guest_tenant_uuid');
            $clearGuestCookie = true;
        }
        if ($userTenantUuid) {
            $request->session()->put('tenant_uuid', $userTenantUuid);
        }

        $response = redirect()->intended(route('home', absolute: false));
        if ($clearGuestCookie) {
            $response->withCookie(Cookie::forget(GuestIdentityService::COOKIE_NAME));
        }

        return $response;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('home', ['logged_out' => 1]);
    }
}
