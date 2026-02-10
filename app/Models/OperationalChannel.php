<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalChannel extends BaseTenantModel
{
    protected $table = 'operational_channels';

    protected $fillable = [
        'tenant_uuid',
        'operational_record_id',
        'channel_type',
        'value',
        'value_normalized',
        'label',
        'is_primary',
        'is_verified',
        'can_contact',
        'last_used_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'can_contact' => 'boolean',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(OperationalRecord::class, 'operational_record_id');
    }
}
