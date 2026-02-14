<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $role) {
            if (!$role->guard_name) {
                $role->guard_name = 'web';
            }
        });
    }

    protected $table = 'users_groups';

    protected $fillable = [
        'name',
        'guard_name',
        'tenant_uuid',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'users_groups_permission', 'role_id', 'permission_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_groups_user', 'role_id', 'user_id');
    }

    public function hasPermissionTo(string $permissionName): bool
    {
        return $this->permissions()
            ->where('name', $permissionName)
            ->exists();
    }

    public function syncPermissionsByName(array $permissionNames): void
    {
        $ids = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $this->permissions()->sync($ids);
    }
}
