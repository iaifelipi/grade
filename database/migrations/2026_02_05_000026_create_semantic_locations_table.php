<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('semantic_locations')) {
            return;
        }

        Schema::create('semantic_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->foreignId('lead_source_semantic_id')->constrained('lead_source_semantics')->cascadeOnDelete();
            $table->string('type', 16)->index();
            $table->unsignedBigInteger('ref_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semantic_locations');
    }
};
