<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalBulkTask extends BaseTenantModel
{
    protected $table = 'operational_bulk_tasks';

    protected $fillable = [
        'tenant_uuid',
        'task_uuid',
        'status',
        'scope_type',
        'action_type',
        'scope_json',
        'action_json',
        'total_items',
        'processed_items',
        'success_items',
        'failed_items',
        'created_by',
        'started_at',
        'finished_at',
        'last_error',
        'summary_json',
    ];

    protected $casts = [
        'scope_json' => 'array',
        'action_json' => 'array',
        'summary_json' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OperationalBulkTaskItem::class, 'task_id');
    }
}
