<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('record_interactions') || !Schema::hasColumn('record_interactions', 'automation_event_id')) {
            return;
        }

        // Deduplicate before enforcing hard uniqueness.
        $duplicates = DB::table('record_interactions')
            ->select('tenant_uuid', 'automation_event_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->whereNotNull('automation_event_id')
            ->groupBy('tenant_uuid', 'automation_event_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('record_interactions')
                ->where('tenant_uuid', (string) $row->tenant_uuid)
                ->where('automation_event_id', (int) $row->automation_event_id)
                ->where('id', '!=', (int) $row->keep_id)
                ->delete();
        }

        if (!$this->indexExists('record_interactions', 'record_interactions_tenant_event_unique')) {
            Schema::table('record_interactions', function (Blueprint $table): void {
                $table->unique(['tenant_uuid', 'automation_event_id'], 'record_interactions_tenant_event_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('record_interactions')) {
            return;
        }

        if ($this->indexExists('record_interactions', 'record_interactions_tenant_event_unique')) {
            Schema::table('record_interactions', function (Blueprint $table): void {
                $table->dropUnique('record_interactions_tenant_event_unique');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', $db)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();

        return (bool) $exists;
    }
};
