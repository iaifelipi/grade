<?php

namespace App\Http\Middleware;

use App\Services\Security\SecurityAccessEventWriter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SecurityAccessLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        /** @var SymfonyResponse $response */
        $response = $next($request);

        $status = (int) $response->getStatusCode();
        if (!in_array($status, [401, 403, 429], true)) {
            return $response;
        }

        // Avoid logging noise for the security dashboard itself.
        $path = '/' . ltrim((string) $request->path(), '/');
        if (str_starts_with($path, '/admin/security')) {
            return $response;
        }

        $user = $request->user();
        app(SecurityAccessEventWriter::class)->write([
            'source' => 'app',
            'event_type' => $status === 429 ? 'rate_limited' : ($status === 401 ? 'unauthenticated' : 'forbidden'),
            'ip_address' => $request->ip(),
            'request_path' => $path,
            'request_method' => $request->method(),
            'http_status' => $status,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_agent' => (string) $request->userAgent(),
            'occurred_at' => now(),
            'payload_json' => [
                'route' => optional($request->route())->getName(),
            ],
        ]);

        return $response;
    }
}

