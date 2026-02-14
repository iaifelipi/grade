<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_users') && Schema::hasColumn('tenant_users', 'plan_id')) {
            $this->dropForeignIfExists('tenant_users', 'tenant_users_plan_id_fk');
            $this->dropIndexIfExists('tenant_users', 'tenant_users_plan_id_idx');

            Schema::table('tenant_users', function (Blueprint $table) {
                $table->dropColumn('plan_id');
            });
        }

        if (Schema::hasTable('tenant_subscriptions') && Schema::hasColumn('tenant_subscriptions', 'plan_id')) {
            $this->dropForeignIfExists('tenant_subscriptions', 'tenant_subscriptions_plan_fk');
            $this->dropIndexIfExists('tenant_subscriptions', 'tenant_subscriptions_plan_id_index');

            Schema::table('tenant_subscriptions', function (Blueprint $table) {
                $table->dropColumn('plan_id');
            });
        }

        if (Schema::hasTable('monetization_price_plans') && Schema::hasColumn('monetization_price_plans', 'plan_id')) {
            $this->dropForeignIfExists('monetization_price_plans', 'mpp_plan_id_fk');
            $this->dropIndexIfExists('monetization_price_plans', 'mpp_plan_id_idx');

            Schema::table('monetization_price_plans', function (Blueprint $table) {
                $table->dropColumn('plan_id');
            });
        }

        if (Schema::hasTable('plans')) {
            Schema::drop('plans');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 50)->unique();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_paid')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('monetization_price_plans') && !Schema::hasColumn('monetization_price_plans', 'plan_id')) {
            Schema::table('monetization_price_plans', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('id');
                $table->index('plan_id', 'mpp_plan_id_idx');
            });

            Schema::table('monetization_price_plans', function (Blueprint $table) {
                $table->foreign('plan_id', 'mpp_plan_id_fk')
                    ->references('id')
                    ->on('plans')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('tenant_subscriptions') && !Schema::hasColumn('tenant_subscriptions', 'plan_id')) {
            Schema::table('tenant_subscriptions', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('tenant_uuid');
                $table->index('plan_id', 'tenant_subscriptions_plan_id_index');
            });

            Schema::table('tenant_subscriptions', function (Blueprint $table) {
                $table->foreign('plan_id', 'tenant_subscriptions_plan_fk')
                    ->references('id')
                    ->on('plans')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('tenant_users') && !Schema::hasColumn('tenant_users', 'plan_id')) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('price_plan_id');
                $table->index('plan_id', 'tenant_users_plan_id_idx');
            });

            Schema::table('tenant_users', function (Blueprint $table) {
                $table->foreign('plan_id', 'tenant_users_plan_id_fk')
                    ->references('id')
                    ->on('plans')
                    ->nullOnDelete();
            });
        }
    }

    private function dropForeignIfExists(string $table, string $foreignName): void
    {
        $create = collect(DB::select("SHOW CREATE TABLE {$table}"))
            ->map(fn ($row) => (string) (array_values((array) $row)[1] ?? ''))
            ->first();

        if ($create && str_contains($create, "CONSTRAINT `{$foreignName}`")) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($foreignName) {
                $tableBlueprint->dropForeign($foreignName);
            });
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'"))->isNotEmpty();
        if ($exists) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($indexName) {
                $tableBlueprint->dropIndex($indexName);
            });
        }
    }
};
