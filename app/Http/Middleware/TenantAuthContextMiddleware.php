<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantAuthContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tenantUser = Auth::guard('tenant')->user();

        if ($tenantUser && !empty($tenantUser->tenant_uuid)) {
            app()->instance('tenant_uuid', (string) $tenantUser->tenant_uuid);
            view()->share('tenant_uuid', (string) $tenantUser->tenant_uuid);
            view()->share('tenant_auth_user', $tenantUser);
        } else {
            view()->share('tenant_auth_user', null);
        }

        return $next($request);
    }
}
