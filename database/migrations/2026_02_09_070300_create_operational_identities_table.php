<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operational_identities')) {
            return;
        }

        Schema::create('operational_identities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_uuid')->index();
            $table->unsignedBigInteger('operational_record_id')->index();
            $table->string('identity_type', 40)->index();
            $table->string('identity_key', 190);
            $table->boolean('is_primary')->default(false)->index();
            $table->decimal('confidence', 5, 2)->default(100);
            $table->string('source', 120)->nullable();
            $table->timestamps();

            $table->foreign('operational_record_id')
                ->references('id')
                ->on('operational_records')
                ->cascadeOnDelete();

            $table->unique(
                ['tenant_uuid', 'identity_type', 'identity_key'],
                'operational_identities_unique_key'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_identities');
    }
};
