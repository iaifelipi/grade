<?php

namespace App\Services\Tenants;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantDeletionService
{
    /**
     * Deletes a tenant and all rows in the current database that reference it by `tenant_uuid`.
     *
     * This project intentionally uses `tenant_uuid` as a loose foreign key (no DB constraints),
     * so we must do best-effort cleanup across all tables.
     *
     * @return array{tenant_uuid:string,deleted_tables:array<string,int>,deleted_private_files:bool,tenant_deleted:bool}
     */
    public function deleteTenantAndAllData(string $tenantUuid): array
    {
        $tenantUuid = trim($tenantUuid);
        if ($tenantUuid === '') {
            return [
                'tenant_uuid' => '',
                'deleted_tables' => [],
                'deleted_private_files' => false,
                'tenant_deleted' => false,
            ];
        }

        $deletedTables = [];
        $deletedPrivate = false;
        $tenantDeleted = false;

        DB::transaction(function () use ($tenantUuid, &$deletedTables, &$deletedPrivate, &$tenantDeleted) {
            // 1) Delete rows by tenant_uuid across all tables that contain the column.
            $tables = $this->tablesWithTenantUuidColumn();
            foreach ($tables as $table) {
                // Tenants table uses `uuid`, not `tenant_uuid`.
                if ($table === 'tenants') {
                    continue;
                }

                $deletedTables[$table] = (int) DB::table($table)
                    ->where('tenant_uuid', $tenantUuid)
                    ->delete();
            }

            // 2) Delete private storage for this tenant.
            // Disk 'private' root is `storage/app/private/tenants`, and paths are `{uuid}/...`.
            try {
                $deletedPrivate = Storage::disk('private')->deleteDirectory($tenantUuid);
            } catch (\Throwable $e) {
                $deletedPrivate = false;
            }

            // 3) Delete the tenant row itself.
            $tenant = Tenant::query()->where('uuid', $tenantUuid)->first();
            if ($tenant) {
                $tenant->delete();
                $tenantDeleted = true;
            }
        });

        return [
            'tenant_uuid' => $tenantUuid,
            'deleted_tables' => $deletedTables,
            'deleted_private_files' => $deletedPrivate,
            'tenant_deleted' => $tenantDeleted,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function tablesWithTenantUuidColumn(): array
    {
        $rows = DB::select(
            "SELECT table_name AS name
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND column_name = 'tenant_uuid'"
        );

        $tables = [];
        foreach ($rows as $row) {
            $name = (string) ($row->name ?? '');
            if ($name !== '') {
                $tables[] = $name;
            }
        }

        // Stable order makes logs easier to read/debug.
        sort($tables);

        return $tables;
    }
}

