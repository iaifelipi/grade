<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $groupsTable = Schema::hasTable('roles') && !Schema::hasTable('users_groups')
            ? 'roles'
            : 'users_groups';

        if (Schema::hasTable($groupsTable)) {
            $legacyUnique = "{$groupsTable}_name_guard_name_unique";
            $tenantUnique = "{$groupsTable}_tenant_uuid_name_guard_name_unique";

            $hasUnique = DB::select("SHOW INDEX FROM {$groupsTable} WHERE Key_name = '{$legacyUnique}'");
            if (!empty($hasUnique)) {
                DB::statement("ALTER TABLE {$groupsTable} DROP INDEX {$legacyUnique}");
            }

            Schema::table($groupsTable, function (Blueprint $table) use ($groupsTable) {
                if (!Schema::hasColumn($groupsTable, 'tenant_uuid')) {
                    $table->string('tenant_uuid')->nullable()->after('guard_name');
                    $table->index('tenant_uuid');
                }
            });

            $hasTeamUnique = DB::select("SHOW INDEX FROM {$groupsTable} WHERE Key_name = '{$tenantUnique}'");
            if (empty($hasTeamUnique)) {
                DB::statement("ALTER TABLE {$groupsTable} ADD UNIQUE {$tenantUnique} (tenant_uuid, name, guard_name)");
            }
        }

        if (Schema::hasTable('model_has_roles')) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                if (!Schema::hasColumn('model_has_roles', 'tenant_uuid')) {
                    $table->string('tenant_uuid')->nullable()->after('role_id');
                    $table->index('tenant_uuid');
                }
            });
        }

        if (Schema::hasTable('model_has_permissions')) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                if (!Schema::hasColumn('model_has_permissions', 'tenant_uuid')) {
                    $table->string('tenant_uuid')->nullable()->after('permission_id');
                    $table->index('tenant_uuid');
                }
            });
        }

        if (Schema::hasTable('users') && Schema::hasTable($groupsTable)) {
            $tenantUuid = DB::table('users')->whereNotNull('tenant_uuid')->value('tenant_uuid');
            if ($tenantUuid) {
                DB::table($groupsTable)->whereNull('tenant_uuid')->update(['tenant_uuid' => $tenantUuid]);
                if (Schema::hasTable('model_has_roles')) {
                    DB::statement("
                        UPDATE model_has_roles m
                        JOIN users u ON u.id = m.model_id AND m.model_type = 'App\\\\Models\\\\User'
                        SET m.tenant_uuid = u.tenant_uuid
                        WHERE m.tenant_uuid IS NULL
                    ");
                }
                if (Schema::hasTable('model_has_permissions')) {
                    DB::statement("
                        UPDATE model_has_permissions m
                        JOIN users u ON u.id = m.model_id AND m.model_type = 'App\\\\Models\\\\User'
                        SET m.tenant_uuid = u.tenant_uuid
                        WHERE m.tenant_uuid IS NULL
                    ");
                }
            }
        }
    }

    public function down(): void
    {
        $groupsTable = Schema::hasTable('roles') && !Schema::hasTable('users_groups')
            ? 'roles'
            : 'users_groups';

        if (Schema::hasTable('model_has_permissions')) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                if (Schema::hasColumn('model_has_permissions', 'tenant_uuid')) {
                    $table->dropIndex(['tenant_uuid']);
                    $table->dropColumn('tenant_uuid');
                }
            });
        }

        if (Schema::hasTable('model_has_roles')) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                if (Schema::hasColumn('model_has_roles', 'tenant_uuid')) {
                    $table->dropIndex(['tenant_uuid']);
                    $table->dropColumn('tenant_uuid');
                }
            });
        }

        if (Schema::hasTable($groupsTable)) {
            $tenantUnique = "{$groupsTable}_tenant_uuid_name_guard_name_unique";
            $legacyUnique = "{$groupsTable}_name_guard_name_unique";

            Schema::table($groupsTable, function (Blueprint $table) use ($groupsTable, $tenantUnique) {
                if (Schema::hasColumn($groupsTable, 'tenant_uuid')) {
                    $hasTenantUnique = DB::select("SHOW INDEX FROM {$groupsTable} WHERE Key_name = '{$tenantUnique}'");
                    if (!empty($hasTenantUnique)) {
                        DB::statement("ALTER TABLE {$groupsTable} DROP INDEX {$tenantUnique}");
                    }
                    $table->dropIndex(['tenant_uuid']);
                    $table->dropColumn('tenant_uuid');
                }
            });

            $hasUnique = DB::select("SHOW INDEX FROM {$groupsTable} WHERE Key_name = '{$legacyUnique}'");
            if (empty($hasUnique)) {
                DB::statement("ALTER TABLE {$groupsTable} ADD UNIQUE {$legacyUnique} (name, guard_name)");
            }
        }
    }
};
