<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminReadOnlyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->isSuperAdmin()) {
            return $next($request);
        }

        // Admin module remains globally writable for superadmin, except
        // taxonomy mutations that must respect read-only global mode.
        if ($request->routeIs('admin.*') && !$request->routeIs('admin.semantic.*')) {
            return $next($request);
        }

        $isImpersonating = $request->session()->has('impersonate_user_id')
            || app()->bound('impersonated_user');
        if ($isImpersonating) {
            return $next($request);
        }

        $tenantOverride = trim((string) $request->session()->get('tenant_uuid_override', ''));
        if ($tenantOverride !== '') {
            return $next($request);
        }

        if ($this->isReadOnlyAllowed($request)) {
            return $next($request);
        }

        $message = 'Superadmin em modo global é somente leitura. Selecione um tenant alvo ou use impersonação para alterar dados.';

        if ($request->expectsJson() || $request->wantsJson() || $request->is('vault/*')) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'code' => 'superadmin_read_only',
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()->back()->with('error', $message);
    }

    private function isReadOnlyAllowed(Request $request): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $routeName = (string) optional($request->route())->getName();
        if (in_array($routeName, $this->allowedWriteRoutesForReadOnlyMode(), true)) {
            return true;
        }

        return false;
    }

    /**
     * Some endpoints use POST only to generate previews (no persistence).
     *
     * @return array<int,string>
     */
    private function allowedWriteRoutesForReadOnlyMode(): array
    {
        return [
            // Superadmin can always update own profile/preferences.
            'profile.update',
            'profile.modal.update',
            'profile.preferences',
            'admin.users.impersonate',
            'admin.impersonate.stop',
            // Global integrations are system-wide, not tenant data.
            'admin.integrations.store',
            'admin.integrations.update',
            'admin.integrations.destroy',
            'admin.integrations.test',
            'explore.dataQuality.preview',
            'vault.sources.purgeAll',
            'vault.sources.purgeSelected',
            'admin.monitoring.queueRestart',
            'admin.monitoring.recoverQueue',
            'admin.monitoring.incidentsAck',
        ];
    }
}
