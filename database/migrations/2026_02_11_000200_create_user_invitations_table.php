<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('email', 190);
            $table->string('name', 120)->nullable();
            $table->string('tenant_uuid', 64)->nullable();
            $table->json('roles_json')->nullable();
            $table->unsignedBigInteger('inviter_user_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'accepted_at']);
            $table->index(['tenant_uuid', 'accepted_at']);
            $table->index(['inviter_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};

