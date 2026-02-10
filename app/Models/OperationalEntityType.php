<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalEntityType extends Model
{
    protected $table = 'operational_entity_types';

    protected $fillable = [
        'tenant_uuid',
        'key',
        'label',
        'is_active',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];
}

