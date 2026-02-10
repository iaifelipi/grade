<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;

class ExploreViewPreference extends BaseTenantModel
{
    protected $table = 'explore_view_preferences';

    protected $fillable = [
        'tenant_uuid',
        'user_id',
        'lead_source_id',
        'scope_key',
        'visible_columns',
        'column_order',
    ];

    protected $casts = [
        'lead_source_id' => 'integer',
        'visible_columns' => 'array',
        'column_order' => 'array',
    ];
}
