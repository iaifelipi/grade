<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_overrides')) {
            return;
        }

        Schema::create('lead_overrides', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->unsignedBigInteger('lead_source_id')->index();
            $table->unsignedBigInteger('lead_id')->index();
            $table->string('column_key', 120)->index();
            $table->text('value_text')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['tenant_uuid', 'lead_source_id', 'lead_id', 'column_key'],
                'lead_overrides_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_overrides');
    }
};
