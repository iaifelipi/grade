<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            $hasUnique = DB::select("SHOW INDEX FROM roles WHERE Key_name = 'roles_name_guard_name_unique'");
            if (!empty($hasUnique)) {
                DB::statement("ALTER TABLE roles DROP INDEX roles_name_guard_name_unique");
            }

            Schema::table('roles', function (Blueprint $table) {
                if (!Schema::hasColumn('roles', 'tenant_uuid')) {
                    $table->string('tenant_uuid')->nullable()->after('guard_name');
                    $table->index('tenant_uuid');
                }
            });

            $hasTeamUnique = DB::select("SHOW INDEX FROM roles WHERE Key_name = 'roles_tenant_uuid_name_guard_name_unique'");
            if (empty($hasTeamUnique)) {
                DB::statement("ALTER TABLE roles ADD UNIQUE roles_tenant_uuid_name_guard_name_unique (tenant_uuid, name, guard_name)");
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

        if (Schema::hasTable('users') && Schema::hasTable('roles')) {
            $tenantUuid = DB::table('users')->whereNotNull('tenant_uuid')->value('tenant_uuid');
            if ($tenantUuid) {
                DB::table('roles')->whereNull('tenant_uuid')->update(['tenant_uuid' => $tenantUuid]);
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

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (Schema::hasColumn('roles', 'tenant_uuid')) {
                    $table->dropUnique(['tenant_uuid', 'name', 'guard_name']);
                    $table->dropIndex(['tenant_uuid']);
                    $table->dropColumn('tenant_uuid');
                }
            });

            $hasUnique = DB::select("SHOW INDEX FROM roles WHERE Key_name = 'roles_name_guard_name_unique'");
            if (empty($hasUnique)) {
                DB::statement("ALTER TABLE roles ADD UNIQUE roles_name_guard_name_unique (name, guard_name)");
            }
        }
    }
};
