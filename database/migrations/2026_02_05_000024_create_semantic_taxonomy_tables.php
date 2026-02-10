<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('semantic_segments')) {
            Schema::create('semantic_segments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('semantic_niches')) {
            Schema::create('semantic_niches', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('semantic_origins')) {
            Schema::create('semantic_origins', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('semantic_countries')) {
            Schema::create('semantic_countries', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('semantic_states')) {
            Schema::create('semantic_states', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->index();
                $table->string('uf', 4)->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('semantic_cities')) {
            Schema::create('semantic_cities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->index();
                $table->foreignId('state_id')->nullable()->constrained('semantic_states')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('semantic_cities');
        Schema::dropIfExists('semantic_states');
        Schema::dropIfExists('semantic_countries');
        Schema::dropIfExists('semantic_origins');
        Schema::dropIfExists('semantic_niches');
        Schema::dropIfExists('semantic_segments');
    }
};
