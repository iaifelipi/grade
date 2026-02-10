<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Base\BaseTenantModel;

class LeadRaw extends BaseTenantModel
{
    protected $table = 'lead_raw';

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    | payload_json é ESSENCIAL para o extras cache funcionar
    */
    protected $fillable = [
        'tenant_uuid',
        'lead_source_id',
        'row_num',
        'name',
        'email',
        'cpf',
        'phone_e164',
        'identity_key',

        // ⭐ CORREÇÃO CRÍTICA (faltava)
        'payload_json',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'payload_json' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relações
    |--------------------------------------------------------------------------
    */
    public function source(): BelongsTo
    {
        return $this->belongsTo(
            LeadSource::class,
            'lead_source_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public function hasEmail(): bool
    {
        return filled($this->email);
    }

    public function hasPhone(): bool
    {
        return filled($this->phone_e164);
    }

    public function hasCpf(): bool
    {
        return filled($this->cpf);
    }
}

