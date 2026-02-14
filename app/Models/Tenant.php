<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    protected $fillable = [
        'uuid',
        'name',
        'plan',
        'slug',
    ];

    protected $casts = [
        'uuid' => 'string',
        'plan' => 'string',
        'slug' => 'string',
    ];


    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    | Gera UUID automático
    */
    protected static function booted(): void
    {
        static::creating(function ($tenant) {
            if (!$tenant->uuid) {
                $tenant->uuid = (string) Str::uuid();
            }
            if (!$tenant->slug && $tenant->name) {
                $base = Str::slug($tenant->name);
                $tenant->slug = $base ?: Str::random(8);
            }
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function users()
    {
        return $this->hasMany(User::class, 'tenant_uuid', 'uuid');
    }

    public function tenantUsers()
    {
        return $this->hasMany(TenantUser::class, 'tenant_id');
    }

    public function tenantUserGroups()
    {
        return $this->hasMany(TenantUserGroup::class, 'tenant_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class, 'tenant_id');
    }

    public function currentSubscription()
    {
        return $this->hasOne(TenantSubscription::class, 'tenant_id')
            ->where('status', 'active')
            ->latestOfMany('id');
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers Grade
    |--------------------------------------------------------------------------
    */

    public function storageRoot(): string
    {
        return "{$this->uuid}";
    }
}
