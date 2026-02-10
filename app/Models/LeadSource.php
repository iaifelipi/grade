<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Models\Traits\HasTenantScope;

class LeadSource extends Model
{
    use HasTenantScope;

    protected $table = 'lead_sources';

    protected $fillable = [
        'tenant_uuid',
        'parent_source_id',
        'source_kind',
        'original_name',
        'file_path',
        'file_ext',
        'file_size_bytes',
        'file_hash',
        'status',
        'last_error',
        'mapping_json',
        'derived_from',
        'cancel_requested',
        'processed_rows',
        'progress_percent',
        'started_at',
        'finished_at',
        'inserted_rows',
        'skipped_rows',
        'total_rows',
        'created_by',
        'extras_cache_status',
        'extras_cache_started_at',
        'extras_cache_finished_at',
        'semantic_anchor',
    ];

    protected $casts = [
        'mapping_json' => 'array',
        'derived_from' => 'array',
        'cancel_requested' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function raws(): HasMany
    {
        return $this->hasMany(LeadRaw::class);
    }

    public function semantic(): HasOne
    {
        return $this->hasOne(LeadSourceSemantic::class);
    }
}
