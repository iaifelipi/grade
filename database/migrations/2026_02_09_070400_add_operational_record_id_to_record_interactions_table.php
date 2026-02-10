<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('record_interactions')) {
            return;
        }

        Schema::table('record_interactions', function (Blueprint $table) {
            if (!Schema::hasColumn('record_interactions', 'operational_record_id')) {
                $table->unsignedBigInteger('operational_record_id')->nullable()->after('tenant_uuid')->index();

                if (Schema::hasTable('operational_records')) {
                    $table->foreign('operational_record_id')
                        ->references('id')
                        ->on('operational_records')
                        ->nullOnDelete();
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('record_interactions')) {
            return;
        }

        Schema::table('record_interactions', function (Blueprint $table) {
            if (Schema::hasColumn('record_interactions', 'operational_record_id')) {
                $table->dropForeign(['operational_record_id']);
                $table->dropColumn('operational_record_id');
            }
        });
    }
};
