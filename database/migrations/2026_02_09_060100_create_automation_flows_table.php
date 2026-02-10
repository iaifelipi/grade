<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automation_flows')) {
            return;
        }

        Schema::create('automation_flows', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->string('name', 160);
            $table->string('status', 32)->default('draft')->index();
            $table->string('trigger_type', 40)->default('manual')->index();
            $table->json('trigger_config')->nullable();
            $table->json('audience_filter')->nullable();
            $table->json('goal_config')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_run_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['tenant_uuid', 'name'], 'automation_flows_unique_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_flows');
    }
};
