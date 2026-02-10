<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalIdentity extends BaseTenantModel
{
    protected $table = 'operational_identities';

    protected $fillable = [
        'tenant_uuid',
        'operational_record_id',
        'identity_type',
        'identity_key',
        'is_primary',
        'confidence',
        'source',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'confidence' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(OperationalRecord::class, 'operational_record_id');
    }
}
