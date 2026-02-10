<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalBulkTaskItem extends BaseTenantModel
{
    protected $table = 'operational_bulk_task_items';

    protected $fillable = [
        'tenant_uuid',
        'task_id',
        'operational_record_id',
        'status',
        'error_message',
        'result_json',
        'processed_at',
    ];

    protected $casts = [
        'result_json' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(OperationalBulkTask::class, 'task_id');
    }
}
