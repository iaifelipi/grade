<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automation_flow_steps')) {
            return;
        }

        Schema::create('automation_flow_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->unsignedBigInteger('flow_id')->index();
            $table->unsignedInteger('step_order')->default(1);
            $table->string('step_type', 40)->index();
            $table->string('channel', 24)->nullable()->index();
            $table->json('config_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('flow_id')
                ->references('id')
                ->on('automation_flows')
                ->cascadeOnDelete();

            $table->unique(['flow_id', 'step_order'], 'automation_flow_steps_unique_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_flow_steps');
    }
};
