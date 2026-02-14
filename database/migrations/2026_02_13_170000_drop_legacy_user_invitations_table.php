<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_invitations');
    }

    public function down(): void
    {
        if (Schema::hasTable('user_invitations')) {
            return;
        }

        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('email', 190);
            $table->string('name', 120)->nullable();
            $table->uuid('tenant_uuid')->nullable()->index();
            $table->json('roles_json')->nullable();
            $table->foreignId('inviter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('accepted_at')->nullable()->index();
            $table->timestamps();

            $table->index(['email', 'accepted_at']);
        });
    }
};

