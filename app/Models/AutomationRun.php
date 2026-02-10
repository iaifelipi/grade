<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRun extends BaseTenantModel
{
    protected $table = 'automation_runs';

    protected $fillable = [
        'tenant_uuid',
        'flow_id',
        'status',
        'started_by_type',
        'started_by_id',
        'context_json',
        'scheduled_count',
        'processed_count',
        'success_count',
        'failure_count',
        'started_at',
        'finished_at',
        'last_error',
    ];

    protected $casts = [
        'context_json' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AutomationFlow::class, 'flow_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AutomationEvent::class, 'run_id')->latest('id');
    }
}
