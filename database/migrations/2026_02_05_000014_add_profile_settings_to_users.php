<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 10)->nullable()->after('is_super_admin');
            }
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('locale');
            }
            if (!Schema::hasColumn('users', 'theme')) {
                $table->string('theme', 16)->nullable()->after('timezone');
            }
            if (!Schema::hasColumn('users', 'location_city')) {
                $table->string('location_city', 120)->nullable()->after('theme');
            }
            if (!Schema::hasColumn('users', 'location_state')) {
                $table->string('location_state', 120)->nullable()->after('location_city');
            }
            if (!Schema::hasColumn('users', 'location_country')) {
                $table->string('location_country', 120)->nullable()->after('location_state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'location_country')) {
                $table->dropColumn('location_country');
            }
            if (Schema::hasColumn('users', 'location_state')) {
                $table->dropColumn('location_state');
            }
            if (Schema::hasColumn('users', 'location_city')) {
                $table->dropColumn('location_city');
            }
            if (Schema::hasColumn('users', 'theme')) {
                $table->dropColumn('theme');
            }
            if (Schema::hasColumn('users', 'timezone')) {
                $table->dropColumn('timezone');
            }
            if (Schema::hasColumn('users', 'locale')) {
                $table->dropColumn('locale');
            }
        });
    }
};
