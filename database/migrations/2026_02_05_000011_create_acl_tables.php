<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $groupsTable = Schema::hasTable('roles') && !Schema::hasTable('users_groups')
            ? 'roles'
            : 'users_groups';
        $userGroupPivot = Schema::hasTable('user_role') && !Schema::hasTable('users_groups_user')
            ? 'user_role'
            : 'users_groups_user';
        $groupPermissionPivot = Schema::hasTable('role_permission') && !Schema::hasTable('users_groups_permission')
            ? 'role_permission'
            : 'users_groups_permission';

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable($groupsTable)) {
            Schema::create($groupsTable, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->string('tenant_uuid')->nullable()->index();
                $table->timestamps();
                $table->unique(['tenant_uuid', 'name', 'guard_name']);
            });
        }

        if (!Schema::hasTable($groupPermissionPivot)) {
            Schema::create($groupPermissionPivot, function (Blueprint $table) use ($groupsTable) {
                $table->foreignId('role_id')->constrained($groupsTable)->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
                $table->primary(['role_id', 'permission_id']);
            });
        }

        if (!Schema::hasTable($userGroupPivot)) {
            Schema::create($userGroupPivot, function (Blueprint $table) use ($groupsTable) {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained($groupsTable)->cascadeOnDelete();
                $table->primary(['user_id', 'role_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users_groups_user')) {
            Schema::dropIfExists('users_groups_user');
        }
        if (Schema::hasTable('user_role')) {
            Schema::dropIfExists('user_role');
        }
        if (Schema::hasTable('users_groups_permission')) {
            Schema::dropIfExists('users_groups_permission');
        }
        if (Schema::hasTable('role_permission')) {
            Schema::dropIfExists('role_permission');
        }
        if (Schema::hasTable('users_groups')) {
            Schema::dropIfExists('users_groups');
        }
        if (Schema::hasTable('roles')) {
            Schema::dropIfExists('roles');
        }
        if (Schema::hasTable('permissions')) {
            Schema::dropIfExists('permissions');
        }
    }
};
