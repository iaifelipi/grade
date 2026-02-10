<?php

namespace App\Http\Middleware;

use App\Services\SensitiveAuditAccessService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaskSensitiveAuditFieldsMiddleware
{
    /** @var string[] */
    private array $sensitiveKeys = ['ip_raw', 'ip_enc', 'ua_raw'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!($response instanceof JsonResponse)) {
            return $response;
        }

        $data = $response->getData(true);
        if (!is_array($data)) {
            return $response;
        }

        $sensitiveCount = $this->countSensitiveFields($data);

        if ($this->canViewSensitive($request)) {
            if ($sensitiveCount > 0) {
                app(SensitiveAuditAccessService::class)->logView(
                    $request,
                    $sensitiveCount,
                    $response->getStatusCode()
                );
            }
            return $response;
        }

        $response->setData($this->maskRecursive($data));
        return $response;
    }

    private function canViewSensitive(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return method_exists($user, 'hasPermission')
            ? (bool) $user->hasPermission('audit.view_sensitive')
            : false;
    }

    private function maskRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, $this->sensitiveKeys, true)) {
                $value[$key] = null;
                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->maskRecursive($item);
            }
        }

        return $value;
    }

    private function countSensitiveFields(array $value): int
    {
        $count = 0;
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, $this->sensitiveKeys, true)) {
                $count++;
            }

            if (is_array($item)) {
                $count += $this->countSensitiveFields($item);
            }
        }

        return $count;
    }
}
