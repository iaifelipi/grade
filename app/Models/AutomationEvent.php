<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationEvent extends BaseTenantModel
{
    protected $table = 'automation_events';

    protected $fillable = [
        'tenant_uuid',
        'flow_id',
        'flow_step_id',
        'run_id',
        'idempotency_key',
        'lead_id',
        'lead_source_id',
        'event_type',
        'channel',
        'status',
        'external_ref',
        'payload_json',
        'response_json',
        'attempt',
        'occurred_at',
        'error_message',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'response_json' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'run_id');
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AutomationFlow::class, 'flow_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AutomationFlowStep::class, 'flow_step_id');
    }
}
