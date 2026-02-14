<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricePlan extends Model
{
    use HasFactory;

    protected $table = 'monetization_price_plans';

    protected $fillable = [
        'code',
        'name',
        'description',
        'billing_interval',
        'amount_minor',
        'currency_code',
        'is_active',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'is_active' => 'boolean',
            'metadata_json' => 'array',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'price_plan_id');
    }
}
