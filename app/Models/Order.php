<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $table = 'monetization_orders';

    protected $fillable = [
        'order_number',
        'tenant_uuid',
        'user_id',
        'gateway_id',
        'price_plan_id',
        'promo_code_id',
        'tax_rate_id',
        'currency_code',
        'subtotal_minor',
        'discount_minor',
        'tax_minor',
        'total_minor',
        'status',
        'payment_status',
        'paid_at',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }

    public function pricePlan(): BelongsTo
    {
        return $this->belongsTo(PricePlan::class, 'price_plan_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }
}
