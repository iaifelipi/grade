<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('security_access_events')) {
            Schema::create('security_access_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('source', 40); // app|cloudflare|server
                $table->string('event_type', 60); // login_failed, waf_block, etc
                $table->string('ip_address', 64)->nullable()->index();
                $table->string('request_path', 255)->nullable();
                $table->string('request_method', 12)->nullable();
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_email', 190)->nullable()->index();
                $table->string('country', 10)->nullable()->index();
                $table->string('asn', 40)->nullable()->index();
                $table->string('user_agent', 255)->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamp('occurred_at')->index();
            });
        }

        if (!Schema::hasTable('security_access_incidents')) {
            Schema::create('security_access_incidents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('level', 20)->default('info'); // info|warning|critical
                $table->string('status', 30)->default('open'); // open|acknowledged|resolved
                $table->string('title', 180);
                $table->string('key', 190)->unique(); // for upsert/correlation (e.g. brute_force:ip:1.2.3.4)
                $table->unsignedInteger('event_count')->default(0);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();

                $table->timestamp('acknowledged_at')->nullable();
                $table->unsignedBigInteger('acknowledged_by_user_id')->nullable()->index();
                $table->text('ack_comment')->nullable();

                $table->json('context_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('security_access_actions')) {
            Schema::create('security_access_actions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('incident_id')->nullable()->index();
                $table->string('action', 60); // block_ip, challenge_ip, ingest_cloudflare, ack_incident, etc
                $table->string('target_type', 40)->nullable(); // ip|user|route|incident
                $table->string('target_value', 190)->nullable()->index();
                $table->string('result', 30)->default('unknown'); // queued|success|failed|unknown
                $table->string('message', 255)->nullable();
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->json('context_json')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('security_access_actions');
        Schema::dropIfExists('security_access_incidents');
        Schema::dropIfExists('security_access_events');
    }
};

