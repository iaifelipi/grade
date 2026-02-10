<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationalRecord extends BaseTenantModel
{
    use SoftDeletes;

    protected $table = 'operational_records';

    protected $fillable = [
        'tenant_uuid',
        'legacy_lead_id',
        'lead_source_id',
        'entity_type',
        'lifecycle_stage',
        'status',
        'display_name',
        'document_number',
        'primary_email',
        'primary_phone_e164',
        'primary_whatsapp_e164',
        'city',
        'uf',
        'sex',
        'score',
        'consent_email',
        'consent_sms',
        'consent_whatsapp',
        'consent_source',
        'consent_at',
        'last_interaction_at',
        'next_action_at',
        'metadata_json',
    ];

    protected $casts = [
        'score' => 'integer',
        'consent_email' => 'boolean',
        'consent_sms' => 'boolean',
        'consent_whatsapp' => 'boolean',
        'metadata_json' => 'array',
        'consent_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'next_action_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function channels(): HasMany
    {
        return $this->hasMany(OperationalChannel::class, 'operational_record_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(OperationalConsent::class, 'operational_record_id');
    }

    public function identities(): HasMany
    {
        return $this->hasMany(OperationalIdentity::class, 'operational_record_id');
    }
}
