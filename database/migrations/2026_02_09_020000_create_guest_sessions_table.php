<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('guest_uuid', 36)->unique();
            $table->char('tenant_uuid', 36)->nullable()->index();
            $table->string('session_id', 191)->nullable()->index();
            $table->char('ip_hash', 64)->nullable();
            $table->char('ua_hash', 64)->nullable();
            $table->string('last_route', 255)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('first_seen_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_sessions');
    }
};

