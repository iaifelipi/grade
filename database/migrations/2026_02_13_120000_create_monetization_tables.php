<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['payment_gateways', 'currencies', 'tax_rates', 'price_plans', 'promo_codes', 'orders'] as $name) {
            $type = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
                ->where('TABLE_NAME', $name)
                ->value('TABLE_TYPE');
            if ($type === 'VIEW') {
                DB::statement('DROP VIEW IF EXISTS `' . str_replace('`', '``', $name) . '`');
            }
        }

        if (!Schema::hasTable('payment_gateways')) {
            Schema::create('payment_gateways', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name', 120);
                $table->string('provider', 80);
                $table->boolean('is_active')->default(true)->index();
                $table->decimal('fee_percent', 6, 3)->default(0);
                $table->bigInteger('fee_fixed_minor')->default(0);
                $table->json('config_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 3)->unique();
                $table->string('name', 80);
                $table->string('symbol', 8)->nullable();
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_default')->default(false)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tax_rates')) {
            Schema::create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('country_code', 2)->nullable()->index();
                $table->string('state_code', 16)->nullable()->index();
                $table->string('city', 120)->nullable()->index();
                $table->decimal('rate_percent', 7, 4);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('price_plans')) {
            Schema::create('price_plans', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->string('billing_interval', 24)->default('monthly')->index();
                $table->bigInteger('amount_minor')->default(0);
                $table->string('currency_code', 3)->default('BRL')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->json('metadata_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('promo_codes')) {
            Schema::create('promo_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name', 120)->nullable();
                $table->string('discount_type', 16)->default('percent')->index();
                $table->decimal('discount_value', 10, 2)->default(0);
                $table->string('currency_code', 3)->nullable()->index();
                $table->unsignedInteger('max_redemptions')->nullable();
                $table->unsignedInteger('redeemed_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 40)->unique();
                $table->string('tenant_uuid', 64)->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('gateway_id')->nullable()->index();
                $table->unsignedBigInteger('price_plan_id')->nullable()->index();
                $table->unsignedBigInteger('promo_code_id')->nullable()->index();
                $table->unsignedBigInteger('tax_rate_id')->nullable()->index();
                $table->string('currency_code', 3)->default('BRL')->index();
                $table->bigInteger('subtotal_minor')->default(0);
                $table->bigInteger('discount_minor')->default(0);
                $table->bigInteger('tax_minor')->default(0);
                $table->bigInteger('total_minor')->default(0);
                $table->string('status', 32)->default('pending')->index();
                $table->string('payment_status', 32)->default('unpaid')->index();
                $table->timestamp('paid_at')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('gateway_id')->references('id')->on('payment_gateways')->nullOnDelete();
                $table->foreign('price_plan_id')->references('id')->on('price_plans')->nullOnDelete();
                $table->foreign('promo_code_id')->references('id')->on('promo_codes')->nullOnDelete();
                $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropForeign(['gateway_id']);
                $table->dropForeign(['price_plan_id']);
                $table->dropForeign(['promo_code_id']);
                $table->dropForeign(['tax_rate_id']);
            });
        }

        Schema::dropIfExists('orders');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('price_plans');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('payment_gateways');
    }
};
