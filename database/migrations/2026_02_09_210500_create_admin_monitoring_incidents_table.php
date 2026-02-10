<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_monitoring_incidents')) {
            return;
        }

        Schema::create('admin_monitoring_incidents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('tenant_uuid', 64)->nullable()->index();
            $table->string('action', 40)->index();
            $table->string('queue_name', 64)->nullable()->index();
            $table->string('outcome', 24)->default('ok')->index();
            $table->string('message', 255)->nullable();
            $table->json('context_json')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_monitoring_incidents');
    }
};
