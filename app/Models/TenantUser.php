<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class TenantUser extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'tenant_users';

    protected $fillable = [
        'tenant_id',
        'tenant_uuid',
        'source_user_id',
        'group_id',
        'price_plan_id',
        'invited_by_tenant_user_id',
        'first_name',
        'last_name',
        'name',
        'email',
        'username',
        'password',
        'phone_e164',
        'birth_date',
        'locale',
        'timezone',
        'status',
        'is_owner',
        'is_primary_account',
        'send_welcome_email',
        'must_change_password',
        'avatar_path',
        'avatar_disk',
        'avatar_updated_at',
        'invited_at',
        'accepted_at',
        'deactivate_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'price_plan_id' => 'integer',
            'is_owner' => 'boolean',
            'is_primary_account' => 'boolean',
            'send_welcome_email' => 'boolean',
            'must_change_password' => 'boolean',
            'avatar_updated_at' => 'datetime',
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'deactivate_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TenantUserGroup::class, 'group_id');
    }

    public function pricePlan(): BelongsTo
    {
        return $this->belongsTo(PricePlan::class, 'price_plan_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by_tenant_user_id');
    }

    public function invitationsCreated(): HasMany
    {
        return $this->hasMany(TenantUserInvitation::class, 'inviter_tenant_user_id');
    }

    public function scopeTenant(Builder $query, string $tenantUuid): Builder
    {
        return $query->where('tenant_uuid', $tenantUuid);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deactivate_at === null;
    }

    public function isSuperAdmin(): bool
    {
        return false;
    }

    public function hasPermission(string $permissionName, ?string $tenantUuid = null): bool
    {
        return false;
    }

    public function hasTenantPermission(string $permission): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ((bool) $this->is_owner || (bool) $this->is_primary_account) {
            return true;
        }

        $group = $this->relationLoaded('group') ? $this->group : $this->group()->first();
        if (!$group || !$group->is_active) {
            return false;
        }

        $permissions = collect($group->permissions_json ?? [])->map(fn ($p) => (string) $p)->filter()->values();

        return $permissions->contains('*') || $permissions->contains($permission);
    }
}
