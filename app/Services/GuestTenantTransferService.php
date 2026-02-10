<?php

namespace App\Services;

use App\Models\LeadSource;
use App\Models\LeadSourceSemantic;
use App\Models\SemanticLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class GuestTenantTransferService
{
    public function transfer(string $guestUuid, string $targetUuid, ?int $userId = null): void
    {
        if (!$guestUuid || !$targetUuid || $guestUuid === $targetUuid) {
            return;
        }

        $sources = LeadSource::withoutGlobalScopes()
            ->where('tenant_uuid', $guestUuid)
            ->get();

        if ($sources->isEmpty()) {
            return;
        }

        $sourceIds = $sources->pluck('id')->all();

        foreach ($sources as $source) {
            $oldPath = (string) ($source->file_path ?? '');
            $newPath = $oldPath;
            if ($oldPath !== '' && str_starts_with($oldPath, 'tenants_guest/' . $guestUuid . '/')) {
                $newPath = $targetUuid . '/' . substr($oldPath, strlen('tenants_guest/' . $guestUuid . '/'));
                $dir = trim(str_replace('\\', '/', dirname($newPath)), '/');
                if ($dir !== '.' && $dir !== '') {
                    Storage::disk('private')->makeDirectory($dir);
                }
                if (Storage::disk('private')->exists($oldPath)) {
                    Storage::disk('private')->move($oldPath, $newPath);
                }
            } elseif ($oldPath !== '' && str_starts_with($oldPath, $guestUuid . '/')) {
                $newPath = $targetUuid . '/' . substr($oldPath, strlen($guestUuid) + 1);
                $dir = trim(str_replace('\\', '/', dirname($newPath)), '/');
                if ($dir !== '.' && $dir !== '') {
                    Storage::disk('private')->makeDirectory($dir);
                }
                if (Storage::disk('private')->exists($oldPath)) {
                    Storage::disk('private')->move($oldPath, $newPath);
                }
            }

            $source->tenant_uuid = $targetUuid;
            $source->file_path = $newPath;
            if ($userId && !$source->created_by) {
                $source->created_by = $userId;
            }
            $source->save();
        }

        DB::table('lead_raw')
            ->where('tenant_uuid', $guestUuid)
            ->whereIn('lead_source_id', $sourceIds)
            ->update(['tenant_uuid' => $targetUuid]);

        DB::table('leads_normalized')
            ->where('tenant_uuid', $guestUuid)
            ->whereIn('lead_source_id', $sourceIds)
            ->update(['tenant_uuid' => $targetUuid]);

        DB::table('lead_overrides')
            ->where('tenant_uuid', $guestUuid)
            ->whereIn('lead_source_id', $sourceIds)
            ->update(['tenant_uuid' => $targetUuid]);

        DB::table('lead_column_settings')
            ->where('tenant_uuid', $guestUuid)
            ->whereIn('lead_source_id', $sourceIds)
            ->update(['tenant_uuid' => $targetUuid]);

        $semanticIds = LeadSourceSemantic::withoutGlobalScopes()
            ->where('tenant_uuid', $guestUuid)
            ->whereIn('lead_source_id', $sourceIds)
            ->pluck('id')
            ->all();

        LeadSourceSemantic::withoutGlobalScopes()
            ->where('tenant_uuid', $guestUuid)
            ->whereIn('lead_source_id', $sourceIds)
            ->update(['tenant_uuid' => $targetUuid]);

        if ($semanticIds) {
            SemanticLocation::withoutGlobalScopes()
                ->where('tenant_uuid', $guestUuid)
                ->whereIn('lead_source_semantic_id', $semanticIds)
                ->update(['tenant_uuid' => $targetUuid]);
        }

        if (Schema::hasTable('guest_sessions')) {
            $update = [];
            if (Schema::hasColumn('guest_sessions', 'status')) {
                $update['status'] = 'migrated';
            }
            if (Schema::hasColumn('guest_sessions', 'migrated_at')) {
                $update['migrated_at'] = now();
            }
            if (Schema::hasColumn('guest_sessions', 'updated_at')) {
                $update['updated_at'] = now();
            }
            if (Schema::hasColumn('guest_sessions', 'migrated_to_user_id')) {
                $update['migrated_to_user_id'] = $userId;
            }
            if ($update) {
                DB::table('guest_sessions')
                    ->where('guest_uuid', $guestUuid)
                    ->update($update);
            }
        }
    }
}
