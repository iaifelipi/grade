<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BugReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_uuid',
        'user_id',
        'name',
        'email',
        'message',
        'steps',
        'url',
        'user_agent',
    ];
}
