<?php

namespace App\Models;

use App\Models\Base\BaseTenantModel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadNormalized extends BaseTenantModel
{
    protected $table = 'leads_normalized';

    protected $fillable = [
        'public_uid',
        'tenant_uuid',
        'lead_source_id',

        'name',
        'cpf',
        'phone',
        'phone_e164',
        'email',
        'entity_type',
        'lifecycle_stage',
        'whatsapp_e164',

        'city',
        'uf',
        'sex',

        'score',

        'segment_id',
        'niche_id',
        'origin_id',
        'optin_email',
        'optin_sms',
        'optin_whatsapp',
        'consent_source',
        'consent_at',
        'last_interaction_at',
        'next_action_at',

        'extras_json',
    ];

    protected $casts = [
        'score' => 'integer',
        'optin_email' => 'boolean',
        'optin_sms' => 'boolean',
        'optin_whatsapp' => 'boolean',
        'consent_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'next_action_at' => 'datetime',
        'extras_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            static $hasPublicUidColumn;
            if ($hasPublicUidColumn === null) {
                $hasPublicUidColumn = Schema::hasColumn('leads_normalized', 'public_uid');
            }
            if (!$hasPublicUidColumn || !empty($model->public_uid)) {
                return;
            }
            $model->public_uid = static::generateUniquePublicUid();
        });
    }

    public static function generateUniquePublicUid(): string
    {
        do {
            $candidate = 'm' . strtolower(Str::random(13));
        } while (static::withoutGlobalScopes()->where('public_uid', $candidate)->exists());

        return $candidate;
    }
}
