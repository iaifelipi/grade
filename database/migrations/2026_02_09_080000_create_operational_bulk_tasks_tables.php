<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('operational_bulk_tasks')) {
            Schema::create('operational_bulk_tasks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tenant_uuid')->index();
                $table->uuid('task_uuid')->unique();
                $table->string('status', 32)->default('queued')->index();
                $table->string('scope_type', 24)->default('selected_ids')->index();
                $table->string('action_type', 40)->index();
                $table->json('scope_json')->nullable();
                $table->json('action_json')->nullable();
                $table->unsignedInteger('total_items')->default(0);
                $table->unsignedInteger('processed_items')->default(0);
                $table->unsignedInteger('success_items')->default(0);
                $table->unsignedInteger('failed_items')->default(0);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamp('started_at')->nullable()->index();
                $table->timestamp('finished_at')->nullable()->index();
                $table->text('last_error')->nullable();
                $table->json('summary_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('operational_bulk_task_items')) {
            Schema::create('operational_bulk_task_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tenant_uuid')->index();
                $table->unsignedBigInteger('task_id')->index();
                $table->unsignedBigInteger('operational_record_id')->nullable()->index();
                $table->string('status', 24)->default('queued')->index();
                $table->text('error_message')->nullable();
                $table->json('result_json')->nullable();
                $table->timestamp('processed_at')->nullable()->index();
                $table->timestamps();

                $table->foreign('task_id')
                    ->references('id')
                    ->on('operational_bulk_tasks')
                    ->cascadeOnDelete();

                if (Schema::hasTable('operational_records')) {
                    $table->foreign('operational_record_id')
                        ->references('id')
                        ->on('operational_records')
                        ->nullOnDelete();
                }

                $table->unique(['task_id', 'operational_record_id'], 'bulk_task_items_unique_record');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_bulk_task_items');
        Schema::dropIfExists('operational_bulk_tasks');
    }
};
