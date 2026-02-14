<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantUserGroup extends Model
{
    use HasFactory;

    protected $table = 'tenant_user_groups';

    protected $fillable = [
        'tenant_id',
        'tenant_uuid',
        'name',
        'slug',
        'description',
        'is_default',
        'is_active',
        'permissions_json',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'permissions_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'group_id');
    }
}
