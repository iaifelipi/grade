<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automation_runs')) {
            return;
        }

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->unsignedBigInteger('flow_id')->index();
            $table->string('status', 32)->default('queued')->index();
            $table->string('started_by_type', 24)->default('system')->index();
            $table->unsignedBigInteger('started_by_id')->nullable()->index();
            $table->json('context_json')->nullable();
            $table->unsignedInteger('scheduled_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('flow_id')
                ->references('id')
                ->on('automation_flows')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
