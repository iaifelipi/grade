<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasTenantScope;

abstract class BaseTenantModel extends Model
{
    use HasTenantScope;
}
