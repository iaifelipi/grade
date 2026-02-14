<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use App\Models\Traits\HasTenantScope;

class LeadSource extends Model
{
    use HasTenantScope;

    protected $table = 'lead_sources';

    protected $fillable = [
        'public_uid',
        'tenant_uuid',
        'parent_source_id',
        'source_kind',
        'original_name',
        'display_name',
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
        'admin_tags_json',
        'admin_notes',
        'archived_at',
        'archived_by',
        'deleted_at',
        'deleted_by',
    ];

    protected $casts = [
        'mapping_json' => 'array',
        'derived_from' => 'array',
        'admin_tags_json' => 'array',
        'cancel_requested' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'archived_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            static $hasPublicUidColumn;
            if ($hasPublicUidColumn === null) {
                $hasPublicUidColumn = Schema::hasColumn('lead_sources', 'public_uid');
            }

            if (!$hasPublicUidColumn) {
                return;
            }

            if (!empty($model->public_uid)) {
                return;
            }

            $model->public_uid = static::generateUniquePublicUid();
        });
    }

    public static function generateUniquePublicUid(): string
    {
        do {
            // Keeps the id short and readable, e.g. xc288mt7o43f0
            $candidate = 'x' . strtolower(Str::random(13));
        } while (static::withoutGlobalScopes()->where('public_uid', $candidate)->exists());

        return $candidate;
    }

    public function raws(): HasMany
    {
        return $this->hasMany(LeadRaw::class);
    }

    public function semantic(): HasOne
    {
        return $this->hasOne(LeadSourceSemantic::class);
    }
}
