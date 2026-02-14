<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IntegrationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_uuid',
        'integration_id',
        'event_type',
        'status',
        'message',
        'payload_json',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function scopeTenant(Builder $query, string $uuid): Builder
    {
        return $query->where('tenant_uuid', $uuid);
    }
}

