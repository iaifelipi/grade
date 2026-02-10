<?php

namespace App\Services\LeadsVault;

use App\Models\LeadSource;

class LeadSourceStatusService
{
    public function byIds(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, fn (int $id): bool => $id > 0));
        if (!$ids) {
            return collect();
        }

        return LeadSource::query()
            ->whereIn('id', $ids)
            ->orderByDesc('id')
            ->get([
                'id',
                'parent_source_id',
                'original_name',
                'status',
                'progress_percent',
                'processed_rows',
                'inserted_rows',
                'skipped_rows',
                'last_error',
                'created_at',
                'updated_at',
            ]);
    }

    public function latest(int $limit = 50)
    {
        $rows = LeadSource::query()
            ->latest()
            ->get([
                'id',
                'parent_source_id',
                'original_name',
                'status',
                'progress_percent',
                'processed_rows',
                'inserted_rows',
                'skipped_rows',
                'last_error',
                'created_at',
                'updated_at',
            ]);

        $activeByRoot = [];
        foreach ($rows as $row) {
            $rootId = !empty($row->parent_source_id)
                ? (int) $row->parent_source_id
                : (int) $row->id;

            if (
                !isset($activeByRoot[$rootId])
                || $row->updated_at?->gt($activeByRoot[$rootId]->updated_at)
                || (
                    $row->updated_at?->equalTo($activeByRoot[$rootId]->updated_at)
                    && (int) $row->id > (int) $activeByRoot[$rootId]->id
                )
            ) {
                $activeByRoot[$rootId] = $row;
            }
        }

        return collect($activeByRoot)
            ->sortByDesc('id')
            ->take($limit)
            ->values();
    }
}
