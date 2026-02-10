<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('model_has_permissions')) {
            Schema::drop('model_has_permissions');
        }

        if (Schema::hasTable('model_has_roles')) {
            Schema::drop('model_has_roles');
        }

        if (Schema::hasTable('role_has_permissions')) {
            Schema::drop('role_has_permissions');
        }
    }

    public function down(): void
    {
        // sem rollback automático
    }
};
