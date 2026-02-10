<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationFlow extends BaseTenantModel
{
    protected $table = 'automation_flows';

    protected $fillable = [
        'tenant_uuid',
        'name',
        'status',
        'trigger_type',
        'trigger_config',
        'audience_filter',
        'goal_config',
        'created_by',
        'updated_by',
        'published_at',
        'last_run_at',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'audience_filter' => 'array',
        'goal_config' => 'array',
        'published_at' => 'datetime',
        'last_run_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(AutomationFlowStep::class, 'flow_id')->orderBy('step_order');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'flow_id')->latest('id');
    }
}
