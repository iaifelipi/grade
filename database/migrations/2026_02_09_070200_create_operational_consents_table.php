<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operational_consents')) {
            return;
        }

        Schema::create('operational_consents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->unsignedBigInteger('operational_record_id')->index();
            $table->string('purpose', 40)->default('marketing')->index();
            $table->string('channel_type', 24)->nullable()->index();
            $table->string('status', 24)->default('unknown')->index();
            $table->string('legal_basis', 60)->nullable();
            $table->string('source', 120)->nullable();
            $table->json('proof_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('operational_record_id')
                ->references('id')
                ->on('operational_records')
                ->cascadeOnDelete();

            $table->unique(
                ['operational_record_id', 'purpose', 'channel_type'],
                'operational_consents_unique_channel'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_consents');
    }
};
