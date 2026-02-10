<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automation_events')) {
            return;
        }

        Schema::create('automation_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->unsignedBigInteger('flow_id')->nullable()->index();
            $table->unsignedBigInteger('flow_step_id')->nullable()->index();
            $table->unsignedBigInteger('run_id')->nullable()->index();
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->unsignedBigInteger('lead_source_id')->nullable()->index();
            $table->string('event_type', 40)->index();
            $table->string('channel', 24)->nullable()->index();
            $table->string('status', 32)->default('queued')->index();
            $table->string('external_ref', 160)->nullable()->index();
            $table->json('payload_json')->nullable();
            $table->json('response_json')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamp('occurred_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('flow_id')
                ->references('id')
                ->on('automation_flows')
                ->nullOnDelete();

            $table->foreign('flow_step_id')
                ->references('id')
                ->on('automation_flow_steps')
                ->nullOnDelete();

            $table->foreign('run_id')
                ->references('id')
                ->on('automation_runs')
                ->nullOnDelete();

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_events');
    }
};
