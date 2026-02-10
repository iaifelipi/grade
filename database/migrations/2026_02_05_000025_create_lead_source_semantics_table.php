<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_source_semantics')) {
            return;
        }

        Schema::create('lead_source_semantics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->foreignId('lead_source_id')->constrained('lead_sources')->cascadeOnDelete();

            $table->unsignedBigInteger('segment_id')->nullable()->index();
            $table->unsignedBigInteger('niche_id')->nullable()->index();
            $table->unsignedBigInteger('origin_id')->nullable()->index();

            $table->timestamps();

            $table->unique(['tenant_uuid', 'lead_source_id'], 'lead_source_semantics_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_source_semantics');
    }
};
