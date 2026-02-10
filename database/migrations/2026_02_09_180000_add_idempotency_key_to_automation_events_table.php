<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('automation_events')) {
            return;
        }

        if (!Schema::hasColumn('automation_events', 'idempotency_key')) {
            Schema::table('automation_events', function (Blueprint $table): void {
                $table->string('idempotency_key', 80)->nullable()->after('run_id');
            });
        }

        DB::table('automation_events')
            ->whereNull('idempotency_key')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('automation_events')
                        ->where('id', (int) $row->id)
                        ->update([
                            'idempotency_key' => 'legacy_' . sha1('automation_event:' . (int) $row->id),
                            'updated_at' => now(),
                        ]);
                }
            });

        Schema::table('automation_events', function (Blueprint $table): void {
            $table->unique(
                ['tenant_uuid', 'run_id', 'idempotency_key'],
                'automation_events_run_idempotency_unique'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('automation_events')) {
            return;
        }

        Schema::table('automation_events', function (Blueprint $table): void {
            $table->dropUnique('automation_events_run_idempotency_unique');
        });

        if (Schema::hasColumn('automation_events', 'idempotency_key')) {
            Schema::table('automation_events', function (Blueprint $table): void {
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
