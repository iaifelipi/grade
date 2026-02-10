<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('guest_sessions')) {
            return;
        }

        Schema::table('guest_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('guest_sessions', 'status')) {
                $table->string('status', 20)->default('active')->index()->after('last_route');
            }
            if (!Schema::hasColumn('guest_sessions', 'migrated_to_user_id')) {
                $table->unsignedBigInteger('migrated_to_user_id')->nullable()->index()->after('status');
            }
            if (!Schema::hasColumn('guest_sessions', 'migrated_at')) {
                $table->timestamp('migrated_at')->nullable()->index()->after('migrated_to_user_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('guest_sessions')) {
            return;
        }

        Schema::table('guest_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('guest_sessions', 'migrated_at')) {
                $table->dropColumn('migrated_at');
            }
            if (Schema::hasColumn('guest_sessions', 'migrated_to_user_id')) {
                $table->dropColumn('migrated_to_user_id');
            }
            if (Schema::hasColumn('guest_sessions', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};

