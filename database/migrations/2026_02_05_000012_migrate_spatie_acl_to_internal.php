<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $groupPermissionPivot = Schema::hasTable('users_groups_permission') ? 'users_groups_permission' : 'role_permission';
        $userGroupPivot = Schema::hasTable('users_groups_user') ? 'users_groups_user' : 'user_role';

        if (Schema::hasTable('role_has_permissions') && Schema::hasTable($groupPermissionPivot)) {
            $rows = DB::table('role_has_permissions')->select(['role_id', 'permission_id'])->get();
            foreach ($rows as $row) {
                DB::table($groupPermissionPivot)->insertOrIgnore([
                    'role_id' => $row->role_id,
                    'permission_id' => $row->permission_id,
                ]);
            }
        }

        if (Schema::hasTable('model_has_roles') && Schema::hasTable($userGroupPivot)) {
            $rows = DB::table('model_has_roles')
                ->where('model_type', \App\Models\User::class)
                ->select(['role_id', 'model_id'])
                ->get();

            foreach ($rows as $row) {
                DB::table($userGroupPivot)->insertOrIgnore([
                    'user_id' => $row->model_id,
                    'role_id' => $row->role_id,
                ]);
            }
        }
    }

    public function down(): void
    {
        // sem rollback automático
    }
};
