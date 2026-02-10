<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalConsent extends BaseTenantModel
{
    protected $table = 'operational_consents';

    protected $fillable = [
        'tenant_uuid',
        'operational_record_id',
        'purpose',
        'channel_type',
        'status',
        'legal_basis',
        'source',
        'proof_json',
        'metadata_json',
        'occurred_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'proof_json' => 'array',
        'metadata_json' => 'array',
        'occurred_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(OperationalRecord::class, 'operational_record_id');
    }
}
