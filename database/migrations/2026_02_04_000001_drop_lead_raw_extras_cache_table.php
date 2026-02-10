<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_raw_extras_cache')) {
            Schema::drop('lead_raw_extras_cache');
        }
    }

    public function down(): void
    {
        // intentionally left blank; table was removed
    }
};
