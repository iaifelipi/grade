<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_paid',
        'sort_order',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'sort_order' => 'integer',
    ];
}
