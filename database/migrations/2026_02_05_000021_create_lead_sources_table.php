<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_sources')) {
            return;
        }

        Schema::create('lead_sources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->string('original_name');
            $table->string('file_path')->nullable();
            $table->string('file_ext', 16)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('file_hash', 64)->nullable();

            $table->string('status', 32)->default('queued')->index();
            $table->text('last_error')->nullable();

            $table->json('mapping_json')->nullable();
            $table->boolean('cancel_requested')->default(false);

            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('inserted_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('extras_cache_status', 32)->nullable();
            $table->timestamp('extras_cache_started_at')->nullable();
            $table->timestamp('extras_cache_finished_at')->nullable();

            $table->string('semantic_anchor', 120)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sources');
    }
};
