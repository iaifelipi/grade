<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operational_records')) {
            return;
        }

        Schema::create('operational_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->unsignedBigInteger('legacy_lead_id')->nullable()->index();
            $table->unsignedBigInteger('lead_source_id')->nullable()->index();

            $table->string('entity_type', 32)->default('lead')->index();
            $table->string('lifecycle_stage', 40)->nullable()->index();
            $table->string('status', 24)->default('active')->index();

            $table->string('display_name', 190)->nullable()->index();
            $table->string('document_number', 64)->nullable()->index();
            $table->string('primary_email', 190)->nullable()->index();
            $table->string('primary_phone_e164', 32)->nullable()->index();
            $table->string('primary_whatsapp_e164', 32)->nullable()->index();
            $table->string('city', 120)->nullable()->index();
            $table->string('uf', 4)->nullable()->index();
            $table->string('sex', 8)->nullable()->index();

            $table->unsignedTinyInteger('score')->default(0)->index();

            $table->boolean('consent_email')->default(false);
            $table->boolean('consent_sms')->default(false);
            $table->boolean('consent_whatsapp')->default(false);
            $table->string('consent_source', 120)->nullable();
            $table->timestamp('consent_at')->nullable()->index();
            $table->timestamp('last_interaction_at')->nullable()->index();
            $table->timestamp('next_action_at')->nullable()->index();

            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['tenant_uuid', 'legacy_lead_id'], 'operational_records_unique_legacy');

            if (Schema::hasTable('leads_normalized')) {
                $table->foreign('legacy_lead_id')
                    ->references('id')
                    ->on('leads_normalized')
                    ->nullOnDelete();
            }

            if (Schema::hasTable('lead_sources')) {
                $table->foreign('lead_source_id')
                    ->references('id')
                    ->on('lead_sources')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_records');
    }
};
