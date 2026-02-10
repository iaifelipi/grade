<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_column_settings')) {
            return;
        }

        Schema::create('lead_column_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->string('column_key')->index();
            $table->string('label')->nullable();
            $table->string('group_name')->nullable();
            $table->boolean('visible')->default(true);
            $table->string('merge_rule')->nullable(); // fallback | concat | null
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_uuid', 'column_key'], 'lead_column_settings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_column_settings');
    }
};
