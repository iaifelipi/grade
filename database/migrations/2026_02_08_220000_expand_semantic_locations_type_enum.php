<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('semantic_locations')) {
            return;
        }

        DB::statement(
            "ALTER TABLE semantic_locations MODIFY COLUMN type ENUM('city','state','country','segment','niche','origin') NOT NULL"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('semantic_locations')) {
            return;
        }

        DB::statement(
            "ALTER TABLE semantic_locations MODIFY COLUMN type ENUM('city','state','country') NOT NULL"
        );
    }
};

