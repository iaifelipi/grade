<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('record_interactions')) {
            return;
        }

        Schema::create('record_interactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->unsignedBigInteger('lead_source_id')->nullable()->index();
            $table->unsignedBigInteger('automation_event_id')->nullable()->index();
            $table->string('channel', 24)->index();
            $table->string('direction', 16)->default('outbound')->index();
            $table->string('status', 32)->default('new')->index();
            $table->string('subject', 190)->nullable();
            $table->text('message')->nullable();
            $table->json('payload_json')->nullable();
            $table->json('result_json')->nullable();
            $table->string('external_ref', 160)->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            if (Schema::hasTable('leads_normalized')) {
                $table->foreign('lead_id')
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

            if (Schema::hasTable('automation_events')) {
                $table->foreign('automation_event_id')
                    ->references('id')
                    ->on('automation_events')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_interactions');
    }
};
