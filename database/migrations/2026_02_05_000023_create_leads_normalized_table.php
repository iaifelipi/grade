<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads_normalized')) {
            return;
        }

        Schema::create('leads_normalized', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->foreignId('lead_source_id')->constrained('lead_sources')->cascadeOnDelete();
            $table->unsignedInteger('row_num')->default(0);

            $table->string('name')->nullable();
            $table->string('cpf', 32)->nullable()->index();
            $table->string('phone_e164', 32)->nullable()->index();
            $table->string('email')->nullable()->index();

            $table->string('city')->nullable()->index();
            $table->string('uf', 4)->nullable()->index();
            $table->string('sex', 2)->nullable()->index();

            $table->unsignedTinyInteger('score')->default(0)->index();

            $table->unsignedBigInteger('segment_id')->nullable()->index();
            $table->unsignedBigInteger('niche_id')->nullable()->index();
            $table->unsignedBigInteger('origin_id')->nullable()->index();

            $table->json('extras_json')->nullable();

            $table->timestamps();

            $table->unique(['tenant_uuid', 'lead_source_id', 'row_num'], 'leads_norm_unique_row');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads_normalized');
    }
};
