<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_sensitive_access_logs')) {
            return;
        }

        Schema::create('audit_sensitive_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('permission_name', 80)->default('audit.view_sensitive');
            $table->string('route_name', 160)->nullable()->index();
            $table->string('request_path', 255)->nullable()->index();
            $table->string('http_method', 16)->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('sensitive_fields_count')->default(0);
            $table->char('ip_hash', 64)->nullable()->index();
            $table->char('ua_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_sensitive_access_logs');
    }
};

