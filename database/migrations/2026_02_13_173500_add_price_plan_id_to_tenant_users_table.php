<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_users') && !Schema::hasColumn('tenant_users', 'price_plan_id')) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->unsignedBigInteger('price_plan_id')->nullable()->after('group_id');
                $table->index('price_plan_id', 'tenant_users_price_plan_id_idx');
            });

            if (Schema::hasTable('monetization_price_plans')) {
                Schema::table('tenant_users', function (Blueprint $table) {
                    $table->foreign('price_plan_id', 'tenant_users_price_plan_id_fk')
                        ->references('id')
                        ->on('monetization_price_plans')
                        ->nullOnDelete();
                });
            }
        }

        if (
            Schema::hasTable('tenant_users')
            && Schema::hasColumn('tenant_users', 'price_plan_id')
            && Schema::hasTable('tenant_subscriptions')
        ) {
            DB::statement(<<<'SQL'
                UPDATE tenant_users tu
                JOIN tenant_subscriptions ts ON ts.tenant_id = tu.tenant_id AND ts.status = 'active'
                SET tu.price_plan_id = ts.price_plan_id
                WHERE tu.price_plan_id IS NULL
                  AND ts.price_plan_id IS NOT NULL
            SQL);
        }

        if (
            Schema::hasTable('tenant_users')
            && Schema::hasColumn('tenant_users', 'plan_id')
            && Schema::hasColumn('tenant_users', 'price_plan_id')
            && Schema::hasTable('monetization_price_plans')
        ) {
            DB::statement(<<<'SQL'
                UPDATE tenant_users tu
                JOIN monetization_price_plans mpp ON mpp.plan_id = tu.plan_id
                SET tu.price_plan_id = mpp.id
                WHERE tu.price_plan_id IS NULL
                  AND tu.plan_id IS NOT NULL
            SQL);
        }

        if (
            Schema::hasTable('tenant_users')
            && Schema::hasColumn('tenant_users', 'plan_id')
            && Schema::hasColumn('tenant_users', 'price_plan_id')
            && Schema::hasTable('monetization_price_plans')
        ) {
            DB::statement(<<<'SQL'
                UPDATE tenant_users tu
                JOIN monetization_price_plans mpp ON mpp.id = tu.price_plan_id
                SET tu.plan_id = mpp.plan_id
                WHERE mpp.plan_id IS NOT NULL
                  AND (tu.plan_id IS NULL OR tu.plan_id <> mpp.plan_id)
            SQL);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_users') && Schema::hasColumn('tenant_users', 'price_plan_id')) {
            $fkExists = collect(DB::select("SHOW CREATE TABLE tenant_users"))
                ->map(fn ($row) => (string) (array_values((array) $row)[1] ?? ''))
                ->contains(fn ($sql) => str_contains($sql, 'tenant_users_price_plan_id_fk'));

            if ($fkExists) {
                Schema::table('tenant_users', function (Blueprint $table) {
                    $table->dropForeign('tenant_users_price_plan_id_fk');
                });
            }

            $idxExists = collect(DB::select("SHOW INDEX FROM tenant_users WHERE Key_name = 'tenant_users_price_plan_id_idx'"))->isNotEmpty();
            if ($idxExists) {
                Schema::table('tenant_users', function (Blueprint $table) {
                    $table->dropIndex('tenant_users_price_plan_id_idx');
                });
            }

            Schema::table('tenant_users', function (Blueprint $table) {
                $table->dropColumn('price_plan_id');
            });
        }
    }
};
