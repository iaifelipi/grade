<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_reports', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_uuid')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('message');
            $table->text('steps')->nullable();
            $table->text('url')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_reports');
    }
};
