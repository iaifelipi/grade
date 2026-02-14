<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )


    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */
    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | Aliases (como Kernel antigo)
        |--------------------------------------------------------------------------
        */
        $middleware->alias([

            /*
            | Grade Multi-tenant ⭐
            */
            'tenant' => \App\Http\Middleware\TenantContextMiddleware::class,
            'tenant.authctx' => \App\Http\Middleware\TenantAuthContextMiddleware::class,
            'tenant.permission' => \App\Http\Middleware\TenantPermissionMiddleware::class,

            /*
            | ACL interno
            */
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'superadmin.readonly' => \App\Http\Middleware\SuperAdminReadOnlyMiddleware::class,
        ]);

        $middleware->append(\App\Http\Middleware\MaskSensitiveAuditFieldsMiddleware::class);
        $middleware->append(\App\Http\Middleware\SecurityAccessLogMiddleware::class);

        // Avoid 419 on logout when session/token rotated or stale.
        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Middleware global (opcional)
        |--------------------------------------------------------------------------
        | Se quiser que TODA rota autenticada tenha tenant automático,
        | pode registrar globalmente:
        |
        | $middleware->append(\App\Http\Middleware\TenantContextMiddleware::class);
        |
        | Mas recomendo usar por rota: ['auth','tenant']
        */
    })


    /*
    |--------------------------------------------------------------------------
    | Exceptions
    |--------------------------------------------------------------------------
    */
    ->withExceptions(function (Exceptions $exceptions): void {
        $isJsonRequest = static function (Request $request): bool {
            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return true;
            }

            $accept = strtolower((string) $request->header('Accept', ''));
            return str_contains($accept, 'application/json');
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($isJsonRequest) {
            if (!$isJsonRequest($request)) {
                return null;
            }

            return response()->json([
                'ok' => false,
                'code' => 'validation_error',
                'message' => $e->getMessage() ?: 'Dados inválidos.',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isJsonRequest) {
            if ($isJsonRequest($request)) {
                return response()->json([
                    'ok' => false,
                    'code' => 'unauthenticated',
                    'message' => 'Usuário não autenticado.',
                ], 401);
            }

            $guards = $e->guards();
            $loginRoute = in_array('tenant', $guards, true) ? 'login' : 'admin.login';

            return redirect()->guest(route($loginRoute));
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($isJsonRequest) {
            if (!$isJsonRequest($request)) {
                return null;
            }

            return response()->json([
                'ok' => false,
                'code' => 'forbidden',
                'message' => $e->getMessage() ?: 'Permissão negada.',
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($isJsonRequest) {
            if (!$isJsonRequest($request)) {
                return null;
            }

            return response()->json([
                'ok' => false,
                'code' => 'not_found',
                'message' => 'Recurso não encontrado.',
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isJsonRequest) {
            if (!$isJsonRequest($request)) {
                return null;
            }

            $status = (int) $e->getStatusCode();
            $code = match ($status) {
                401 => 'unauthenticated',
                403 => 'forbidden',
                404 => 'not_found',
                422 => 'validation_error',
                429 => 'too_many_requests',
                default => 'http_error',
            };

            return response()->json([
                'ok' => false,
                'code' => $code,
                'message' => $e->getMessage() ?: 'Erro de requisição.',
            ], $status);
        });

        $exceptions->render(function (\Throwable $e, Request $request) use ($isJsonRequest) {
            if (!$isJsonRequest($request)) {
                return null;
            }

            return response()->json([
                'ok' => false,
                'code' => 'internal_error',
                'message' => config('app.debug')
                    ? ($e->getMessage() ?: 'Erro interno.')
                    : 'Erro interno.',
            ], 500);
        });
    })


    ->create();
