<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles') && !Schema::hasTable('users_groups')) {
            Schema::rename('roles', 'users_groups');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users_groups') && !Schema::hasTable('roles')) {
            Schema::rename('users_groups', 'roles');
        }
    }
};

