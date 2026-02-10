<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $permission) {
            if (!$permission->guard_name) {
                $permission->guard_name = 'web';
            }
        });
    }

    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
