<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanChangeLog extends Model
{
    protected $table = 'plan_change_logs';

    protected $fillable = [
        'tenant_id',
        'from_plan',
        'to_plan',
        'changed_by',
        'note',
    ];
}
