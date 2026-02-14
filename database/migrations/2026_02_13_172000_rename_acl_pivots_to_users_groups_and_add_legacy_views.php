<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Rename pivot tables to the new canonical names.
        if (Schema::hasTable('user_role') && !Schema::hasTable('users_groups_user')) {
            Schema::rename('user_role', 'users_groups_user');
        }

        if (Schema::hasTable('role_permission') && !Schema::hasTable('users_groups_permission')) {
            Schema::rename('role_permission', 'users_groups_permission');
        }

        // 2) Legacy fallback: expose old pivot names as simple updatable views.
        // These keep compatibility for legacy SQL/reporting paths that still read old names.
        DB::statement('DROP VIEW IF EXISTS user_role');
        DB::statement('DROP VIEW IF EXISTS role_permission');

        if (Schema::hasTable('users_groups_user')) {
            DB::statement('CREATE VIEW user_role AS SELECT user_id, role_id FROM users_groups_user');
        }

        if (Schema::hasTable('users_groups_permission')) {
            DB::statement('CREATE VIEW role_permission AS SELECT role_id, permission_id FROM users_groups_permission');
        }
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS user_role');
        DB::statement('DROP VIEW IF EXISTS role_permission');

        if (Schema::hasTable('users_groups_user') && !Schema::hasTable('user_role')) {
            Schema::rename('users_groups_user', 'user_role');
        }

        if (Schema::hasTable('users_groups_permission') && !Schema::hasTable('role_permission')) {
            Schema::rename('users_groups_permission', 'role_permission');
        }
    }
};

