<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operational_channels')) {
            return;
        }

        Schema::create('operational_channels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->unsignedBigInteger('operational_record_id')->index();
            $table->string('channel_type', 24)->index();
            $table->string('value', 190);
            $table->string('value_normalized', 190)->index();
            $table->string('label', 60)->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('is_verified')->default(false)->index();
            $table->boolean('can_contact')->default(true)->index();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('operational_record_id')
                ->references('id')
                ->on('operational_records')
                ->cascadeOnDelete();

            $table->unique(
                ['operational_record_id', 'channel_type', 'value_normalized'],
                'operational_channels_unique_value'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_channels');
    }
};
