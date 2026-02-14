<?php

namespace App\Models;

use App\Services\Users\UsernameService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            /** @var UsernameService $svc */
            $svc = app(UsernameService::class);

            $raw = $user->username;
            if (is_string($raw)) {
                $raw = trim($raw);
            }

            if ($raw === null || $raw === '') {
                $email = (string) ($user->email ?? '');
                if ($email !== '') {
                    $user->username = $svc->generateUniqueFromEmail($email);
                }
                return;
            }

            $normalized = $svc->normalize((string) $raw);
            $user->username = $normalized !== '' ? $normalized : null;
        });

        static::saving(function (self $user) {
            if ($user->username === null) {
                return;
            }
            /** @var UsernameService $svc */
            $svc = app(UsernameService::class);
            $normalized = $svc->normalize((string) $user->username);
            $user->username = $normalized !== '' ? $normalized : null;
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'tenant_uuid',
        'name',
        'username',
        'email',
        'avatar_path',
        'password',
        'is_super_admin',
        'locale',
        'timezone',
        'theme',
        'location_city',
        'location_state',
        'location_country',
    ];


    /*
    |--------------------------------------------------------------------------
    | Hidden
    |--------------------------------------------------------------------------
    */
    protected $hidden = [
        'password',
        'remember_token',
    ];


    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_uuid', 'uuid');
    }

    public function conta()
    {
        return $this->tenant();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'users_groups_user', 'user_id', 'role_id');
    }


    /*
    |--------------------------------------------------------------------------
    | Scopes (multi-tenant)
    |--------------------------------------------------------------------------
    */

    public function scopeTenant(Builder $query, string $uuid): Builder
    {
        return $query->where('tenant_uuid', $uuid);
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function tenantUuid(): ?string
    {
        return $this->tenant_uuid;
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function isDisabled(): bool
    {
        return !empty($this->disabled_at);
    }

    public function hasRole(string $roleName, ?string $tenantUuid = null): bool
    {
        $tenantUuid = $tenantUuid ?? (app()->bound('tenant_uuid') ? app('tenant_uuid') : $this->tenant_uuid);

        return $this->roles()
            ->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->where('name', $roleName)
            ->exists();
    }

    public function hasPermission(string $permissionName, ?string $tenantUuid = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $tenantUuid = $tenantUuid ?? (app()->bound('tenant_uuid') ? app('tenant_uuid') : $this->tenant_uuid);

        return $this->roles()
            ->when($tenantUuid, fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->whereHas('permissions', function ($q) use ($permissionName) {
                $q->where('name', $permissionName);
            })
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isOperator(): bool
    {
        return $this->hasRole('operator');
    }

    public function isViewer(): bool
    {
        return $this->hasRole('viewer');
    }
}
