<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->string('tenant_uuid')->nullable()->index();
                $table->timestamps();
                $table->unique(['tenant_uuid', 'name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('role_permission')) {
            Schema::create('role_permission', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
                $table->primary(['role_id', 'permission_id']);
            });
        }

        if (!Schema::hasTable('user_role')) {
            Schema::create('user_role', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->primary(['user_id', 'role_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_role')) {
            Schema::dropIfExists('user_role');
        }
        if (Schema::hasTable('role_permission')) {
            Schema::dropIfExists('role_permission');
        }
        if (Schema::hasTable('roles')) {
            Schema::dropIfExists('roles');
        }
        if (Schema::hasTable('permissions')) {
            Schema::dropIfExists('permissions');
        }
    }
};
