<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Illuminate\Pagination\Paginator;
use App\Models\LeadSource;
use App\Models\LeadNormalized;
use App\Policies\LeadSourcePolicy;
use App\Policies\LeadNormalizedPolicy;

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
    }
}
