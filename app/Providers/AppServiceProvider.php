<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\Paginator;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use App\Models\LeadSource;
use App\Models\LeadNormalized;
use App\Policies\LeadSourcePolicy;
use App\Policies\LeadNormalizedPolicy;
use App\Services\Security\SecurityAccessEventWriter;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Lockout;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RedirectIfAuthenticated::redirectUsing(function () {
            if (Auth::guard('web')->check()) {
                return route('admin.dashboard');
            }

            if (Auth::guard('tenant')->check()) {
                return route('home');
            }

            return route('home');
        });

        // Use Bootstrap pagination views across the app (prevents Tailwind SVG layout issues).
        Paginator::useBootstrapFive();

        Gate::policy(LeadSource::class, LeadSourcePolicy::class);
        Gate::policy(LeadNormalized::class, LeadNormalizedPolicy::class);

        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            if (is_string($ability) && str_contains($ability, '.')) {
                return method_exists($user, 'hasPermission')
                    ? $user->hasPermission($ability)
                    : null;
            }

            return null;
        });

        /*
        |--------------------------------------------------------------------------
        | 🔥 UTF-8 GLOBAL (MySQL)
        |--------------------------------------------------------------------------
        | Evita:
        |  S�o Paulo
        |  Maring�
        |--------------------------------------------------------------------------
        */

        try {
            DB::statement("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            // Evita falha em CLI/tinker quando a conexão não está pronta.
        }


        /*
        |--------------------------------------------------------------------------
        | 🔥 JSON UTF8 GLOBAL (Grade padrão)
        |--------------------------------------------------------------------------
        | Uso:
        |   return response()->jsonUtf8($data);
        |--------------------------------------------------------------------------
        | Evita:
        |  \u00e3 \u00e1 \u00e7
        |--------------------------------------------------------------------------
        */

        Response::macro('jsonUtf8', function ($data, $status = 200, $headers = []) {
            return response()->json(
                $data,
                $status,
                $headers,
                JSON_UNESCAPED_UNICODE
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Security Access Events (app)
        |--------------------------------------------------------------------------
        | Minimal baseline signals to feed /admin/security without needing
        | external providers.
        */
        Event::listen(Login::class, function (Login $event): void {
            $req = request();
            app(SecurityAccessEventWriter::class)->write([
                'source' => 'app',
                'event_type' => 'login_success',
                'ip_address' => method_exists($req, 'ip') ? $req->ip() : null,
                'request_path' => method_exists($req, 'path') ? '/' . ltrim((string) $req->path(), '/') : null,
                'request_method' => method_exists($req, 'method') ? $req->method() : null,
                'http_status' => null,
                'user_id' => $event->user?->id,
                'user_email' => $event->user?->email,
                'user_agent' => (string) $req->userAgent(),
                'occurred_at' => now(),
                'payload_json' => [
                    'guard' => $event->guard,
                    'remember' => (bool) ($event->remember ?? false),
                ],
            ]);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $req = request();
            $email = null;
            $login = null;
            if (is_array($event->credentials ?? null)) {
                $email = $event->credentials['email'] ?? null;
                $login = $event->credentials['login'] ?? ($event->credentials['email'] ?? ($event->credentials['username'] ?? null));
            }
            app(SecurityAccessEventWriter::class)->write([
                'source' => 'app',
                'event_type' => 'login_failed',
                'ip_address' => method_exists($req, 'ip') ? $req->ip() : null,
                'request_path' => method_exists($req, 'path') ? '/' . ltrim((string) $req->path(), '/') : null,
                'request_method' => method_exists($req, 'method') ? $req->method() : null,
                'http_status' => null,
                'user_id' => $event->user?->id,
                'user_email' => $event->user?->email ?? $email,
                'user_agent' => (string) $req->userAgent(),
                'occurred_at' => now(),
                'payload_json' => [
                    'guard' => $event->guard,
                    'has_user' => (bool) ($event->user !== null),
                    'login' => $login,
                ],
            ]);
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            $req = $event->request;
            app(SecurityAccessEventWriter::class)->write([
                'source' => 'app',
                'event_type' => 'login_lockout',
                'ip_address' => method_exists($req, 'ip') ? $req->ip() : null,
                'request_path' => method_exists($req, 'path') ? '/' . ltrim((string) $req->path(), '/') : null,
                'request_method' => method_exists($req, 'method') ? $req->method() : null,
                'http_status' => 429,
                'user_id' => null,
                'user_email' => null,
                'user_agent' => (string) $req->userAgent(),
                'occurred_at' => now(),
                'payload_json' => [
                    'throttle_key' => method_exists($event, 'throttleKey') ? $event->throttleKey : null,
                ],
            ]);
        });
    }
}
