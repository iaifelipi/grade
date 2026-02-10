<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_file_events', function (Blueprint $table) {
            $table->id();
            $table->char('guest_uuid', 36)->index();
            $table->char('tenant_uuid', 36)->nullable()->index();
            $table->string('session_id', 191)->nullable()->index();
            $table->unsignedBigInteger('lead_source_id')->nullable()->index();
            $table->string('action', 50)->index();
            $table->string('file_name', 255)->nullable();
            $table->string('file_path', 1024)->nullable();
            $table->char('file_hash', 64)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('lead_source_id')
                ->references('id')
                ->on('lead_sources')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_file_events');
    }
};

