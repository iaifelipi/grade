<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;

class LeadOverride extends BaseTenantModel
{
    protected $table = 'lead_overrides';

    protected $fillable = [
        'tenant_uuid',
        'lead_source_id',
        'lead_id',
        'column_key',
        'value_text',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'lead_source_id' => 'integer',
        'lead_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
}
