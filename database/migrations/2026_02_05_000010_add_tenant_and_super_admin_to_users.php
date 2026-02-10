<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'tenant_uuid')) {
                $table->string('tenant_uuid')->nullable()->after('email')->index();
            }

            if (!Schema::hasColumn('users', 'is_super_admin')) {
                $table->boolean('is_super_admin')->default(false)->after('tenant_uuid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_super_admin')) {
                $table->dropColumn('is_super_admin');
            }

            if (Schema::hasColumn('users', 'tenant_uuid')) {
                $table->dropIndex(['tenant_uuid']);
                $table->dropColumn('tenant_uuid');
            }
        });
    }
};
