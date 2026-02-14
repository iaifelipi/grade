<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_user_groups')) {
            return;
        }

        Schema::table('tenant_user_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_user_groups', 'permissions_json')) {
                $table->json('permissions_json')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenant_user_groups')) {
            return;
        }

        Schema::table('tenant_user_groups', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_user_groups', 'permissions_json')) {
                $table->dropColumn('permissions_json');
            }
        });
    }
};
