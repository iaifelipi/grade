<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_raw')) {
            return;
        }

        Schema::create('lead_raw', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->foreignId('lead_source_id')->constrained('lead_sources')->cascadeOnDelete();
            $table->unsignedInteger('row_num')->default(0);

            $table->string('name')->nullable();
            $table->string('cpf', 32)->nullable()->index();
            $table->string('phone_e164', 32)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('identity_key', 64)->nullable();

            $table->json('payload_json')->nullable();

            $table->timestamps();

            $table->unique(['tenant_uuid', 'lead_source_id', 'row_num'], 'lead_raw_unique_row');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_raw');
    }
};
