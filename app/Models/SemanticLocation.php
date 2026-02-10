<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasTenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SemanticLocation extends Model
{
    use HasTenantScope;

    protected $table = 'semantic_locations';

    protected $fillable = [
        'tenant_uuid',
        'lead_source_semantic_id',
        'type',
        'ref_id',
    ];

    protected $casts = [
        'ref_id' => 'integer',
    ];

    public function semantic()
    {
        return $this->belongsTo(LeadSourceSemantic::class);
    }

    public function label(): string
    {
        $table = match ($this->type) {
            'city' => 'semantic_cities',
            'state' => 'semantic_states',
            'country' => 'semantic_countries',
            'segment' => 'semantic_segments',
            'niche' => 'semantic_niches',
            'origin' => 'semantic_origins',
            default => null,
        };

        if (!$table) return '';

        $query = DB::table($table)->where('id', $this->ref_id);

        if ($this->type === 'state' && Schema::hasColumn('semantic_states', 'uf')) {
            $uf = $query->value('uf');
            return (string) ($uf ?: $query->value('name'));
        }

        return (string) $query->value('name');
    }
}
