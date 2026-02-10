<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationFlowStep extends BaseTenantModel
{
    protected $table = 'automation_flow_steps';

    protected $fillable = [
        'tenant_uuid',
        'flow_id',
        'step_order',
        'step_type',
        'channel',
        'config_json',
        'is_active',
    ];

    protected $casts = [
        'config_json' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AutomationFlow::class, 'flow_id');
    }
}
