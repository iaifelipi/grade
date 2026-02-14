<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monetization_price_plans') && !Schema::hasColumn('monetization_price_plans', 'plan_id')) {
            Schema::table('monetization_price_plans', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('id');
                $table->index('plan_id', 'mpp_plan_id_idx');
            });

            if (Schema::hasTable('plans')) {
                Schema::table('monetization_price_plans', function (Blueprint $table) {
                    $table->foreign('plan_id', 'mpp_plan_id_fk')
                        ->references('id')
                        ->on('plans')
                        ->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('tenant_users') && !Schema::hasColumn('tenant_users', 'plan_id')) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('group_id');
                $table->index('plan_id', 'tenant_users_plan_id_idx');
            });

            if (Schema::hasTable('plans')) {
                Schema::table('tenant_users', function (Blueprint $table) {
                    $table->foreign('plan_id', 'tenant_users_plan_id_fk')
                        ->references('id')
                        ->on('plans')
                        ->nullOnDelete();
                });
            }
        }

        if (!Schema::hasTable('tenant_subscriptions')) {
            Schema::create('tenant_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('tenant_uuid', 64)->nullable()->index();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->unsignedBigInteger('price_plan_id')->nullable();
                $table->string('status', 32)->default('active')->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status'], 'tenant_subscriptions_tenant_status_idx');
                $table->foreign('tenant_id', 'tenant_subscriptions_tenant_fk')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
                $table->foreign('plan_id', 'tenant_subscriptions_plan_fk')
                    ->references('id')
                    ->on('plans')
                    ->nullOnDelete();
                $table->foreign('price_plan_id', 'tenant_subscriptions_price_plan_fk')
                    ->references('id')
                    ->on('monetization_price_plans')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('plans') && Schema::hasTable('monetization_price_plans')) {
            DB::statement("
                UPDATE monetization_price_plans mpp
                JOIN plans p ON LOWER(p.code) = LOWER(mpp.code)
                SET mpp.plan_id = p.id
                WHERE mpp.plan_id IS NULL
            ");
        }

        if (Schema::hasTable('tenant_subscriptions') && Schema::hasTable('tenants')) {
            $tenants = DB::table('tenants')->select(['id', 'uuid', 'plan'])->get();

            foreach ($tenants as $tenant) {
                $existingActive = DB::table('tenant_subscriptions')
                    ->where('tenant_id', (int) $tenant->id)
                    ->where('status', 'active')
                    ->exists();
                if ($existingActive) {
                    continue;
                }

                $planCode = strtolower(trim((string) ($tenant->plan ?? '')));
                $pricePlan = null;
                if ($planCode !== '' && Schema::hasTable('monetization_price_plans')) {
                    $pricePlan = DB::table('monetization_price_plans')
                        ->whereRaw('LOWER(code) = ?', [$planCode])
                        ->orderByDesc('is_active')
                        ->orderBy('id')
                        ->first(['id', 'plan_id', 'code']);
                }

                $planId = null;
                if ($pricePlan && $pricePlan->plan_id) {
                    $planId = (int) $pricePlan->plan_id;
                } elseif ($planCode !== '' && Schema::hasTable('plans')) {
                    $planId = DB::table('plans')
                        ->whereRaw('LOWER(code) = ?', [$planCode])
                        ->value('id');
                }

                DB::table('tenant_subscriptions')->insert([
                    'tenant_id' => (int) $tenant->id,
                    'tenant_uuid' => (string) ($tenant->uuid ?? ''),
                    'plan_id' => $planId ?: null,
                    'price_plan_id' => $pricePlan ? (int) $pricePlan->id : null,
                    'status' => 'active',
                    'started_at' => now(),
                    'ended_at' => null,
                    'metadata_json' => json_encode(['seeded_from' => 'tenants.plan']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (
            Schema::hasTable('tenant_users')
            && Schema::hasColumn('tenant_users', 'plan_id')
            && Schema::hasTable('tenant_subscriptions')
        ) {
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
        if (Schema::hasTable('tenant_subscriptions')) {
            Schema::table('tenant_subscriptions', function (Blueprint $table) {
                $table->dropForeign('tenant_subscriptions_tenant_fk');
                $table->dropForeign('tenant_subscriptions_plan_fk');
                $table->dropForeign('tenant_subscriptions_price_plan_fk');
            });
            Schema::dropIfExists('tenant_subscriptions');
        }

        if (Schema::hasTable('tenant_users') && Schema::hasColumn('tenant_users', 'plan_id')) {
            $fkExists = collect(DB::select("SHOW CREATE TABLE tenant_users"))
                ->pluck('Create Table')
                ->contains(fn ($sql) => str_contains((string) $sql, 'tenant_users_plan_id_fk'));
            if ($fkExists) {
                Schema::table('tenant_users', function (Blueprint $table) {
                    $table->dropForeign('tenant_users_plan_id_fk');
                });
            }

            $idxExists = collect(DB::select("SHOW INDEX FROM tenant_users WHERE Key_name = 'tenant_users_plan_id_idx'"))->isNotEmpty();
            if ($idxExists) {
                Schema::table('tenant_users', function (Blueprint $table) {
                    $table->dropIndex('tenant_users_plan_id_idx');
                });
            }

            Schema::table('tenant_users', function (Blueprint $table) {
                $table->dropColumn('plan_id');
            });
        }

        if (Schema::hasTable('monetization_price_plans') && Schema::hasColumn('monetization_price_plans', 'plan_id')) {
            $fkExists = collect(DB::select("SHOW CREATE TABLE monetization_price_plans"))
                ->pluck('Create Table')
                ->contains(fn ($sql) => str_contains((string) $sql, 'mpp_plan_id_fk'));
            if ($fkExists) {
                Schema::table('monetization_price_plans', function (Blueprint $table) {
                    $table->dropForeign('mpp_plan_id_fk');
                });
            }

            $idxExists = collect(DB::select("SHOW INDEX FROM monetization_price_plans WHERE Key_name = 'mpp_plan_id_idx'"))->isNotEmpty();
            if ($idxExists) {
                Schema::table('monetization_price_plans', function (Blueprint $table) {
                    $table->dropIndex('mpp_plan_id_idx');
                });
            }

            Schema::table('monetization_price_plans', function (Blueprint $table) {
                $table->dropColumn('plan_id');
            });
        }
    }
};

