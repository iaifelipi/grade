<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;

class LeadColumnSetting extends BaseTenantModel
{
    protected $table = 'lead_column_settings';

    protected $fillable = [
        'tenant_uuid',
        'lead_source_id',
        'column_key',
        'label',
        'group_name',
        'visible',
        'merge_rule',
        'sort_order',
    ];

    protected $casts = [
        'visible' => 'boolean',
        'sort_order' => 'integer',
    ];
}
