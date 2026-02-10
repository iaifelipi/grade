<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_sources', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_sources', 'parent_source_id')) {
                $table->unsignedBigInteger('parent_source_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('lead_sources', 'source_kind')) {
                $table->string('source_kind', 32)->default('original')->after('parent_source_id');
            }
            if (!Schema::hasColumn('lead_sources', 'derived_from')) {
                $table->json('derived_from')->nullable()->after('mapping_json');
            }
        });

        Schema::table('lead_sources', function (Blueprint $table) {
            if (Schema::hasColumn('lead_sources', 'parent_source_id')) {
                $table->foreign('parent_source_id')
                    ->references('id')
                    ->on('lead_sources')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_sources', function (Blueprint $table) {
            if (Schema::hasColumn('lead_sources', 'parent_source_id')) {
                $table->dropForeign(['parent_source_id']);
            }
            if (Schema::hasColumn('lead_sources', 'parent_source_id')) {
                $table->dropColumn('parent_source_id');
            }
            if (Schema::hasColumn('lead_sources', 'source_kind')) {
                $table->dropColumn('source_kind');
            }
            if (Schema::hasColumn('lead_sources', 'derived_from')) {
                $table->dropColumn('derived_from');
            }
        });
    }
};
