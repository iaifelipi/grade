<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('operational_records')) {
            return;
        }

        Schema::table('operational_records', function (Blueprint $table) {
            if (!Schema::hasColumn('operational_records', 'deleted_at')) {
                $table->softDeletes()->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('operational_records')) {
            return;
        }

        Schema::table('operational_records', function (Blueprint $table) {
            if (Schema::hasColumn('operational_records', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};

