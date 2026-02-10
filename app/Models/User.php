<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;


    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'tenant_uuid',
        'name',
        'email',
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
        return $this->belongsToMany(Role::class, 'user_role');
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
