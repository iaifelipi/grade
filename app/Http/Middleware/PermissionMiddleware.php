<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissions)
    {
        $user = $this->resolveEffectiveUser($request);

        if (!$user) {
            abort(403, 'Usuário não autenticado.');
        }

        $abilities = array_filter(array_map('trim', explode('|', $permissions)));

        foreach ($abilities as $ability) {
            if ($user->hasPermission($ability)) {
                return $next($request);
            }
        }

        abort(403, 'Permissão negada.');
    }

    private function resolveEffectiveUser(Request $request): ?User
    {
        $authUser = $request->user();
        if (!$authUser) {
            return null;
        }

        $routeName = (string) optional($request->route())->getName();
        if (in_array($routeName, ['admin.impersonate.stop', 'admin.users.impersonate'], true)) {
            return $authUser;
        }

        $impersonated = app()->bound('impersonated_user') ? app('impersonated_user') : null;
        if ($impersonated instanceof User) {
            return $impersonated;
        }

        $impersonateId = (int) $request->session()->get('impersonate_user_id', 0);
        if ($impersonateId > 0) {
            $candidate = User::query()->find($impersonateId);
            if ($candidate) {
                return $candidate;
            }
        }

        return $authUser;
    }
}
