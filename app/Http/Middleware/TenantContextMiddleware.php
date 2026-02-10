<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\TenantContext;
use App\Models\User;

class TenantContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        /*
        |--------------------------------------------------------------------------
        | Resolve tenant automaticamente
        |--------------------------------------------------------------------------
        */

        $uuid = null;
        $impersonated = null;

        if (auth()->check()) {
            $impersonateId = $request->session()->get('impersonate_user_id');
            if ($impersonateId) {
                $impersonated = User::query()->find($impersonateId);
                if ($impersonated) {
                    $uuid = $impersonated->tenant_uuid;
                } else {
                    $request->session()->forget('impersonate_user_id');
                }
            }
        }

        if (!$uuid) {
            if (auth()->check() && auth()->user()->isSuperAdmin()) {
                $override = $request->session()->get('tenant_uuid_override');
                $uuid = $override ?: null;
            } else {
                $uuid = TenantContext::tenantUuidOrNull();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Bloqueia acesso se logado sem tenant
        |--------------------------------------------------------------------------
        */
        if (auth()->check() && !auth()->user()->isSuperAdmin() && !$uuid) {
            abort(403, 'Tenant não definido.');
        }

        /*
        |--------------------------------------------------------------------------
        | Disponibiliza no container (global)
        |--------------------------------------------------------------------------
        */
        if ($uuid) {
            if (auth()->check()) {
                $tenantExists = \App\Models\Tenant::query()
                    ->where('uuid', $uuid)
                    ->exists();
                if (!$tenantExists) {
                    $name = auth()->user()->name ?? 'Tenant';
                    $baseSlug = \Illuminate\Support\Str::slug((string) $name);
                    $slug = $baseSlug ?: \Illuminate\Support\Str::random(8);
                    if (\App\Models\Tenant::query()->where('slug', $slug)->exists()) {
                        $slug = $slug . '-' . \Illuminate\Support\Str::random(4);
                    }

                    \App\Models\Tenant::create([
                        'uuid' => $uuid,
                        'name' => $name,
                        'plan' => 'free',
                        'slug' => $slug,
                    ]);
                }
            }

            app()->instance('tenant_uuid', $uuid);

            // opcional: compartilhar com views
            view()->share('tenant_uuid', $uuid);
        }

        if ($impersonated) {
            app()->instance('impersonated_user', $impersonated);
            view()->share('impersonated_user', $impersonated);
        } else {
            if (app()->bound('impersonated_user')) {
                app()->forgetInstance('impersonated_user');
            }
            view()->share('impersonated_user', null);
        }

        return $next($request);
    }
}
