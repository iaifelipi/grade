<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = Auth::guard('tenant')->user();

        if (!$user) {
            abort(401, 'Unauthenticated tenant user.');
        }

        if (!method_exists($user, 'hasTenantPermission') || !$user->hasTenantPermission($permission)) {
            abort(403, 'Tenant permission denied: ' . $permission);
        }

        return $next($request);
    }
}
