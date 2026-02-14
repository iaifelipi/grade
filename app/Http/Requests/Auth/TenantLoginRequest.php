<?php

namespace App\Http\Requests\Auth;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant' => ['nullable', 'string', 'max:120'],
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): TenantUser
    {
        $this->ensureIsNotRateLimited();

        $tenantRaw = trim((string) $this->input('tenant', ''));
        $login = trim((string) $this->input('login', ''));
        $password = (string) $this->input('password', '');
        $remember = $this->boolean('remember');

        $tenant = null;
        if ($tenantRaw !== '') {
            $tenant = Tenant::query()
                ->where('slug', $tenantRaw)
                ->orWhere('uuid', $tenantRaw)
                ->first();

            if (!$tenant) {
                RateLimiter::hit($this->throttleKey());
                throw ValidationException::withMessages([
                    'tenant' => 'Conta (tenant) não encontrada.',
                ]);
            }
        } else {
            $tenantUuids = TenantUser::query()
                ->when(
                    filter_var($login, FILTER_VALIDATE_EMAIL),
                    fn ($q) => $q->where('email', mb_strtolower($login)),
                    fn ($q) => $q->where('username', Str::lower($login))
                )
                ->whereNotNull('tenant_uuid')
                ->pluck('tenant_uuid')
                ->filter(fn ($uuid) => trim((string) $uuid) !== '')
                ->map(fn ($uuid) => (string) $uuid)
                ->unique()
                ->values();

            if ($tenantUuids->count() > 1) {
                RateLimiter::hit($this->throttleKey());
                throw ValidationException::withMessages([
                    'tenant' => 'Encontramos mais de uma conta para este login. Informe o tenant.',
                ]);
            }

            if ($tenantUuids->count() === 1) {
                $tenant = Tenant::query()
                    ->where('uuid', (string) $tenantUuids->first())
                    ->first();
            }
        }

        $credentials = [
            'password' => $password,
        ];

        if ($tenant) {
            $credentials['tenant_uuid'] = (string) $tenant->uuid;
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = mb_strtolower($login);
        } else {
            $credentials['username'] = Str::lower($login);
        }

        $ok = Auth::guard('tenant')->attempt($credentials, $remember);
        if (!$ok) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        /** @var TenantUser|null $user */
        $user = Auth::guard('tenant')->user();
        if (!$user || !$user->isActive()) {
            Auth::guard('tenant')->logout();
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'login' => 'Conta desativada ou inativa.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        $seconds = RateLimiter::availableIn($this->throttleKey());
        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        $tenant = trim((string) $this->input('tenant', ''));
        return Str::transliterate(Str::lower(($tenant !== '' ? $tenant : '_auto_') . '|' . $this->string('login') . '|' . $this->ip()));
    }
}
