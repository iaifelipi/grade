<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('integrations')) {
            Schema::create('integrations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tenant_uuid')->index();
                $table->string('provider', 40); // mailwizz|sms_gateway|wasender|custom
                $table->string('key', 64); // stable identifier for code/UI/URLs
                $table->string('name', 120);
                $table->string('status', 20)->default('active'); // active|disabled

                $table->text('secrets_enc')->nullable(); // encrypted:array
                $table->json('settings_json')->nullable();

                $table->timestamp('last_tested_at')->nullable();
                $table->string('last_test_status', 20)->nullable(); // ok|error

                $table->timestamps();

                $table->unique(['tenant_uuid', 'key']);
                $table->index(['tenant_uuid', 'provider']);
            });
        }

        if (!Schema::hasTable('integration_events')) {
            Schema::create('integration_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tenant_uuid')->index();
                $table->unsignedBigInteger('integration_id')->nullable()->index();
                $table->string('event_type', 60); // test, send, webhook, etc
                $table->string('status', 20)->default('info'); // info|ok|error
                $table->string('message', 255)->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamp('occurred_at')->index();

                $table->index(['tenant_uuid', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('integrations');
    }
};

