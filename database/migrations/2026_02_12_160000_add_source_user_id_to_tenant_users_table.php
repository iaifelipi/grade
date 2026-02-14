<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_users')) {
            return;
        }

        if (!Schema::hasColumn('tenant_users', 'source_user_id')) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->unsignedBigInteger('source_user_id')->nullable()->after('tenant_uuid');
                $table->index('source_user_id', 'tenant_users_source_user_idx');
            });
        }

        if (Schema::hasTable('users')) {
            DB::table('tenant_users')
                ->whereNull('source_user_id')
                ->orderBy('id')
                ->select(['id', 'tenant_uuid', 'email', 'username'])
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        $tenantUuid = trim((string) ($row->tenant_uuid ?? ''));
                        if ($tenantUuid === '') {
                            continue;
                        }

                        $email = strtolower(trim((string) ($row->email ?? '')));
                        $username = strtolower(trim((string) ($row->username ?? '')));

                        $userId = null;
                        if ($email !== '') {
                            $userId = DB::table('users')
                                ->where('tenant_uuid', $tenantUuid)
                                ->whereRaw('LOWER(email) = ?', [$email])
                                ->value('id');
                        }

                        if (!$userId && $username !== '') {
                            $userId = DB::table('users')
                                ->where('tenant_uuid', $tenantUuid)
                                ->whereRaw('LOWER(username) = ?', [$username])
                                ->value('id');
                        }

                        if ($userId) {
                            DB::table('tenant_users')
                                ->where('id', (int) $row->id)
                                ->whereNull('source_user_id')
                                ->update(['source_user_id' => (int) $userId]);
                        }
                    }
                });

            $duplicateSourceIds = DB::table('tenant_users')
                ->whereNotNull('source_user_id')
                ->groupBy('source_user_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('source_user_id');

            foreach ($duplicateSourceIds as $sourceUserId) {
                $rows = DB::table('tenant_users')
                    ->where('source_user_id', (int) $sourceUserId)
                    ->orderByRaw('deleted_at IS NULL DESC')
                    ->orderByDesc('id')
                    ->get(['id']);

                $keepId = (int) (($rows->first()->id ?? 0));
                if ($keepId <= 0) {
                    continue;
                }

                DB::table('tenant_users')
                    ->where('source_user_id', (int) $sourceUserId)
                    ->where('id', '!=', $keepId)
                    ->update(['source_user_id' => null]);
            }
        }

        $foreignExists = collect(DB::select("SHOW CREATE TABLE tenant_users"))
            ->pluck('Create Table')
            ->contains(fn ($sql) => str_contains((string) $sql, 'tenant_users_source_user_fk'));

        if (!$foreignExists) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->foreign('source_user_id', 'tenant_users_source_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        $uniqueExists = collect(DB::select("SHOW INDEX FROM tenant_users WHERE Key_name = 'tenant_users_source_user_unique'"))->isNotEmpty();
        if (!$uniqueExists) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->unique('source_user_id', 'tenant_users_source_user_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenant_users') || !Schema::hasColumn('tenant_users', 'source_user_id')) {
            return;
        }

        $foreignExists = collect(DB::select("SHOW CREATE TABLE tenant_users"))
            ->pluck('Create Table')
            ->contains(fn ($sql) => str_contains((string) $sql, 'tenant_users_source_user_fk'));

        if ($foreignExists) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->dropForeign('tenant_users_source_user_fk');
            });
        }

        $uniqueExists = collect(DB::select("SHOW INDEX FROM tenant_users WHERE Key_name = 'tenant_users_source_user_unique'"))->isNotEmpty();
        if ($uniqueExists) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->dropUnique('tenant_users_source_user_unique');
            });
        }

        $indexExists = collect(DB::select("SHOW INDEX FROM tenant_users WHERE Key_name = 'tenant_users_source_user_idx'"))->isNotEmpty();
        if ($indexExists) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->dropIndex('tenant_users_source_user_idx');
            });
        }

        Schema::table('tenant_users', function (Blueprint $table) {
            $table->dropColumn('source_user_id');
        });
    }
};
