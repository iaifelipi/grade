<?php

namespace App\Services;

use App\Models\LeadSourceSemantic;
use App\Models\LeadSource;
use App\Models\SemanticLocation;
use Illuminate\Support\Facades\DB;

class VaultSemanticService
{
    public function load(string $tenantUuid, int $sourceId): array
    {
        $sem = LeadSourceSemantic::with('locations')
            ->where('tenant_uuid', $tenantUuid)
            ->where('lead_source_id', $sourceId)
            ->first();

        $anchor = (string) LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('id', $sourceId)
            ->value('semantic_anchor') ?: 'Brasil';

        if (!$sem) {
            return [
                'locations' => [],
                'segment' => [],
                'niche' => [],
                'origin' => [],
                'anchor' => $anchor,
            ];
        }

        $segmentIds = $this->semanticTypeIds($sem, 'segment');
        if ($sem->segment_id) {
            array_unshift($segmentIds, (int) $sem->segment_id);
        }
        $segmentIds = array_values(array_unique(array_filter($segmentIds)));

        $nicheIds = $this->semanticTypeIds($sem, 'niche');
        if ($sem->niche_id) {
            array_unshift($nicheIds, (int) $sem->niche_id);
        }
        $nicheIds = array_values(array_unique(array_filter($nicheIds)));

        return [
            'locations' => $sem->locations
                ->filter(fn ($l) => in_array($l->type, ['city', 'state', 'country'], true))
                ->map(fn($l) => [
                'type' => $l->type,
                'ref_id' => $l->ref_id,
                'label' => $l->label(),
            ])
                ->values(),
            'segment' => $this->labelsByIds('semantic_segments', $segmentIds),
            'niche'   => $this->labelsByIds('semantic_niches', $nicheIds),
            'origin'  => $this->labels('semantic_origins', $sem->origin_id),
            'anchor'  => $anchor
        ];
    }

    public function save(string $tenantUuid, int $sourceId, array $data): void
    {
        DB::transaction(function () use ($tenantUuid, $sourceId, $data) {

            $sem = LeadSourceSemantic::updateOrCreate(
                [
                    'tenant_uuid' => $tenantUuid,
                    'lead_source_id' => $sourceId
                ],
                [
                    'segment_id' => $data['segment_ids'][0] ?? null,
                    'niche_id'   => $data['niche_ids'][0] ?? null,
                    'origin_id'  => $data['origin_ids'][0] ?? null,
                ]
            );

            SemanticLocation::where('lead_source_semantic_id', $sem->id)->delete();

            foreach ($data['locations'] ?? [] as $loc) {
                SemanticLocation::create([
                    'tenant_uuid' => $tenantUuid,
                    'lead_source_semantic_id' => $sem->id,
                    'type' => $loc['type'],
                    'ref_id' => $loc['ref_id'],
                ]);
            }

            foreach (($data['segment_ids'] ?? []) as $segmentId) {
                $segmentId = (int) $segmentId;
                if ($segmentId <= 0) {
                    continue;
                }
                SemanticLocation::create([
                    'tenant_uuid' => $tenantUuid,
                    'lead_source_semantic_id' => $sem->id,
                    'type' => 'segment',
                    'ref_id' => $segmentId,
                ]);
            }

            foreach (($data['niche_ids'] ?? []) as $nicheId) {
                $nicheId = (int) $nicheId;
                if ($nicheId <= 0) {
                    continue;
                }
                SemanticLocation::create([
                    'tenant_uuid' => $tenantUuid,
                    'lead_source_semantic_id' => $sem->id,
                    'type' => 'niche',
                    'ref_id' => $nicheId,
                ]);
            }

            $anchor = trim((string) ($data['anchor'] ?? '')) ?: 'Brasil';
            LeadSource::query()
                ->where('tenant_uuid', $tenantUuid)
                ->where('id', $sourceId)
                ->update(['semantic_anchor' => $anchor]);
        });
    }

    private function labels(string $table, ?int $id): array
    {
        if (!$id) return [];

        return [[
            'id' => $id,
            'name' => DB::table($table)->where('id', $id)->value('name')
        ]];
    }

    private function labelsByIds(string $table, array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $names = DB::table($table)
            ->whereIn('id', $ids)
            ->pluck('name', 'id');

        $result = [];
        foreach ($ids as $id) {
            $name = (string) ($names[$id] ?? '');
            if ($name === '') {
                continue;
            }
            $result[] = [
                'id' => (int) $id,
                'name' => $name,
            ];
        }

        return $result;
    }

    private function semanticTypeIds(LeadSourceSemantic $sem, string $type): array
    {
        return $sem->locations
            ->where('type', $type)
            ->pluck('ref_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
