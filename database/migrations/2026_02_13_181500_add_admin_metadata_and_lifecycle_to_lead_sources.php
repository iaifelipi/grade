<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_sources')) {
            return;
        }

        Schema::table('lead_sources', function (Blueprint $table): void {
            if (!Schema::hasColumn('lead_sources', 'display_name')) {
                $table->string('display_name')->nullable()->after('original_name');
            }
            if (!Schema::hasColumn('lead_sources', 'admin_tags_json')) {
                $table->json('admin_tags_json')->nullable()->after('semantic_anchor');
            }
            if (!Schema::hasColumn('lead_sources', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('admin_tags_json');
            }
            if (!Schema::hasColumn('lead_sources', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->index()->after('admin_notes');
            }
            if (!Schema::hasColumn('lead_sources', 'archived_by')) {
                $table->unsignedBigInteger('archived_by')->nullable()->index()->after('archived_at');
            }
            if (!Schema::hasColumn('lead_sources', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->index()->after('archived_by');
            }
            if (!Schema::hasColumn('lead_sources', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->index()->after('deleted_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lead_sources')) {
            return;
        }

        Schema::table('lead_sources', function (Blueprint $table): void {
            foreach (['deleted_by', 'deleted_at', 'archived_by', 'archived_at', 'admin_notes', 'admin_tags_json', 'display_name'] as $column) {
                if (Schema::hasColumn('lead_sources', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
