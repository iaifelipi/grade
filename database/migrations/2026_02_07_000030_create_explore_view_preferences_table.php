<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('explore_view_preferences')) {
            return;
        }

        Schema::create('explore_view_preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('lead_source_id')->nullable()->index();
            $table->string('scope_key', 64)->index();
            $table->json('visible_columns')->nullable();
            $table->json('column_order')->nullable();
            $table->timestamps();

            $table->unique(['tenant_uuid', 'user_id', 'scope_key'], 'explore_view_prefs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explore_view_preferences');
    }
};
