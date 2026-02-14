<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AcceptTenantInvitationRequest;
use App\Services\TenantUsers\TenantUserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TenantInviteController extends Controller
{
    public function show(string $token, TenantUserInvitationService $service): View
    {
        $invitation = $service->findByToken($token);
        if (!$invitation) {
            abort(404);
        }
        if ($invitation->isAccepted() || $invitation->isExpired()) {
            abort(410, 'Invitation expired or already used.');
        }

        return view('tenant.invite.accept', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    public function accept(string $token, AcceptTenantInvitationRequest $request, TenantUserInvitationService $service): RedirectResponse
    {
        $invitation = $service->findByToken($token);
        if (!$invitation) {
            abort(404);
        }

        $tenantUser = $service->accept($invitation, $request->validated());

        Auth::guard('tenant')->login($tenantUser);
        $request->session()->regenerate();
        $request->session()->put('tenant_uuid', (string) $tenantUser->tenant_uuid);

        return redirect()->route('home')->with('status', 'Tenant account activated.');
    }
}
