<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUserInvitation extends Model
{
    use HasFactory;

    protected $table = 'tenant_user_invitations';

    protected $fillable = [
        'token_hash',
        'tenant_id',
        'tenant_uuid',
        'email',
        'first_name',
        'last_name',
        'name',
        'group_id',
        'roles_json',
        'permissions_json',
        'inviter_user_id',
        'inviter_tenant_user_id',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'roles_json' => 'array',
            'permissions_json' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TenantUserGroup::class, 'group_id');
    }

    public function inviterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    public function inviterTenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'inviter_tenant_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}

