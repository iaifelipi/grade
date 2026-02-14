<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_uuid',
        'provider',
        'key',
        'name',
        'status',
        'secrets_enc',
        'settings_json',
        'last_tested_at',
        'last_test_status',
    ];

    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
            // Secrets must never be stored in plaintext.
            'secrets_enc' => 'encrypted:array',
            'last_tested_at' => 'datetime',
        ];
    }

    public function scopeTenant(Builder $query, string $uuid): Builder
    {
        return $query->where('tenant_uuid', $uuid);
    }
}

