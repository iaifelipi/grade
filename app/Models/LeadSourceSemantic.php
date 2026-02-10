<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasTenantScope;

class LeadSourceSemantic extends Model
{
    use HasTenantScope;

    protected $table = 'lead_source_semantics';

    protected $fillable = [
        'tenant_uuid',
        'lead_source_id',
        'segment_id',
        'niche_id',
        'origin_id',
    ];

    protected $casts = [
        'segment_id' => 'integer',
        'niche_id' => 'integer',
        'origin_id' => 'integer',
    ];

    public function source()
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function locations()
    {
        return $this->hasMany(SemanticLocation::class);
    }
}
