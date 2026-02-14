<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $table = 'monetization_payment_gateways';

    protected $fillable = [
        'code',
        'name',
        'provider',
        'is_active',
        'fee_percent',
        'fee_fixed_minor',
        'config_json',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fee_percent' => 'decimal:3',
            'fee_fixed_minor' => 'integer',
            'config_json' => 'array',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'gateway_id');
    }
}
