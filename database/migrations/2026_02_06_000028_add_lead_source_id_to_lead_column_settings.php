<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_column_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_column_settings', 'lead_source_id')) {
                $table->unsignedBigInteger('lead_source_id')->nullable()->index()->after('tenant_uuid');
            }
        });

        Schema::table('lead_column_settings', function (Blueprint $table) {
            $table->dropUnique('lead_column_settings_unique');
            $table->unique(['tenant_uuid', 'lead_source_id', 'column_key'], 'lead_column_settings_unique');
        });
    }

    public function down(): void
    {
        Schema::table('lead_column_settings', function (Blueprint $table) {
            $table->dropUnique('lead_column_settings_unique');
            $table->unique(['tenant_uuid', 'column_key'], 'lead_column_settings_unique');
            if (Schema::hasColumn('lead_column_settings', 'lead_source_id')) {
                $table->dropColumn('lead_source_id');
            }
        });
    }
};
