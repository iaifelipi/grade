<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;

class RecordInteraction extends BaseTenantModel
{
    protected $table = 'record_interactions';

    protected $fillable = [
        'tenant_uuid',
        'operational_record_id',
        'lead_id',
        'lead_source_id',
        'automation_event_id',
        'channel',
        'direction',
        'status',
        'subject',
        'message',
        'payload_json',
        'result_json',
        'external_ref',
        'created_by',
        'occurred_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'result_json' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
