<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('integrations')) {
            return;
        }

        // We keep the tenant_uuid column for backward compatibility, but from now on
        // integrations are global: tenant_uuid is always set to 'global'.
        // This avoids schema-alter requirements (DBAL) and keeps existing unique(tenant_uuid, key).
        $global = 'global';

        // Deduplicate by key before collapsing tenant_uuid to 'global' (unique constraint would break).
        // Keep the most recent row (highest id) for each key.
        try {
            DB::statement("
                DELETE i1 FROM integrations i1
                INNER JOIN integrations i2
                    ON i1.`key` = i2.`key`
                   AND i1.id < i2.id
            ");
        } catch (\Throwable $e) {
            // If DB engine doesn't support multi-table delete, skip; operator can handle manually.
        }

        try {
            DB::table('integrations')->where('tenant_uuid', '!=', $global)->update(['tenant_uuid' => $global]);
        } catch (\Throwable $e) {
            // ignore
        }

        if (Schema::hasTable('integration_events') && Schema::hasColumn('integration_events', 'tenant_uuid')) {
            try {
                DB::table('integration_events')->where('tenant_uuid', '!=', $global)->update(['tenant_uuid' => $global]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down(): void
    {
        // No-op: cannot restore per-tenant data once collapsed to a global namespace.
    }
};

