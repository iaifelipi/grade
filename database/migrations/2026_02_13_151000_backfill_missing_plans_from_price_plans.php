<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans') || !Schema::hasTable('monetization_price_plans')) {
            return;
        }

        $rows = DB::table('monetization_price_plans')
            ->whereNull('plan_id')
            ->whereNotNull('code')
            ->get(['id', 'code', 'name', 'amount_minor']);

        foreach ($rows as $row) {
            $code = strtolower(trim((string) ($row->code ?? '')));
            if ($code === '') {
                continue;
            }

            $existingPlanId = DB::table('plans')
                ->whereRaw('LOWER(code) = ?', [$code])
                ->value('id');

            if (!$existingPlanId) {
                $nextSort = (int) DB::table('plans')->max('sort_order') + 1;
                $existingPlanId = DB::table('plans')->insertGetId([
                    'code' => $code,
                    'name' => trim((string) ($row->name ?? '')) !== ''
                        ? (string) $row->name
                        : ucfirst($code),
                    'description' => null,
                    'is_paid' => (int) ($row->amount_minor ?? 0) > 0,
                    'sort_order' => $nextSort > 0 ? $nextSort : 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('monetization_price_plans')
                ->where('id', (int) $row->id)
                ->update([
                    'plan_id' => (int) $existingPlanId,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('tenant_subscriptions')) {
            DB::statement("
                UPDATE tenant_subscriptions ts
                JOIN monetization_price_plans mpp ON mpp.id = ts.price_plan_id
                SET ts.plan_id = mpp.plan_id
                WHERE ts.plan_id IS NULL AND mpp.plan_id IS NOT NULL
            ");
        }

        if (Schema::hasTable('tenant_users') && Schema::hasColumn('tenant_users', 'plan_id') && Schema::hasTable('tenant_subscriptions')) {
            DB::statement("
                UPDATE tenant_users tu
                JOIN tenant_subscriptions ts ON ts.tenant_id = tu.tenant_id AND ts.status = 'active'
                SET tu.plan_id = ts.plan_id
                WHERE tu.plan_id IS NULL AND ts.plan_id IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        // Backfill only; no destructive rollback.
    }
};

