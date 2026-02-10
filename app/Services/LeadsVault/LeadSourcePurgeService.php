<?php

namespace App\Services\LeadsVault;

use App\Models\LeadSource;
use App\Models\LeadSourceSemantic;
use App\Models\SemanticLocation;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadSourcePurgeService
{
    private function activeTenantUuid(): string
    {
        if (app()->bound('tenant_uuid')) {
            $uuid = trim((string) app('tenant_uuid'));
            if ($uuid !== '') {
                return $uuid;
            }
        }

        return TenantStorage::requireTenantUuid();
    }

    public function purgeAll(): int
    {
        $tenantUuid = $this->activeTenantUuid();

        return DB::transaction(function () use ($tenantUuid) {
            $sources = LeadSource::query()
                ->where('tenant_uuid', $tenantUuid)
                ->get(['id', 'file_path']);

            $sourceIds = $sources->pluck('id')->all();

            $this->purgeByIds($tenantUuid, $sources, $sourceIds);

            return count($sourceIds);
        });
    }

    public function purgeSelected(array $ids): int
    {
        $tenantUuid = $this->activeTenantUuid();

        $ids = array_values(array_filter($ids, fn($id) => is_numeric($id)));
        if (!$ids) return 0;

        return DB::transaction(function () use ($tenantUuid, $ids) {
            $sources = LeadSource::query()
                ->where('tenant_uuid', $tenantUuid)
                ->whereIn('id', $ids)
                ->get(['id', 'file_path']);

            $sourceIds = $sources->pluck('id')->all();
            $this->purgeByIds($tenantUuid, $sources, $sourceIds);

            return count($sourceIds);
        });
    }

    private function purgeByIds(string $tenantUuid, $sources, array $sourceIds): void
    {
        if (!$sourceIds) {
            return;
        }

        $semanticIds = LeadSourceSemantic::query()
            ->where('tenant_uuid', $tenantUuid)
            ->whereIn('lead_source_id', $sourceIds)
            ->pluck('id')
            ->all();

        if ($semanticIds) {
            SemanticLocation::query()
                ->whereIn('lead_source_semantic_id', $semanticIds)
                ->delete();
        }

        LeadSourceSemantic::query()
            ->where('tenant_uuid', $tenantUuid)
            ->whereIn('lead_source_id', $sourceIds)
            ->delete();

        if (Schema::hasTable('lead_overrides')) {
            DB::table('lead_overrides')
                ->where('tenant_uuid', $tenantUuid)
                ->whereIn('lead_source_id', $sourceIds)
                ->delete();
        }

        if (Schema::hasTable('lead_column_settings') && Schema::hasColumn('lead_column_settings', 'lead_source_id')) {
            DB::table('lead_column_settings')
                ->where('tenant_uuid', $tenantUuid)
                ->whereIn('lead_source_id', $sourceIds)
                ->delete();
        }

        if (Schema::hasTable('explore_view_preferences')) {
            DB::table('explore_view_preferences')
                ->where('tenant_uuid', $tenantUuid)
                ->whereIn('lead_source_id', $sourceIds)
                ->delete();
        }

        // Delete by lead_source_id to avoid tenant_uuid mismatches causing leftovers.
        if (Schema::hasTable('lead_raw')) {
            DB::table('lead_raw')->whereIn('lead_source_id', $sourceIds)->delete();
        }
        if (Schema::hasTable('leads_normalized')) {
            DB::table('leads_normalized')->whereIn('lead_source_id', $sourceIds)->delete();
        }

        LeadSource::query()
            ->where('tenant_uuid', $tenantUuid)
            ->whereIn('id', $sourceIds)
            ->delete();

        foreach ($sources as $s) {
            if ($s->file_path) {
                TenantStorage::deletePrivate($s->file_path);
            }
        }
    }
}
