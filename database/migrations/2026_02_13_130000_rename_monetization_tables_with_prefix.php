<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string,string>
     */
    private array $tableMap = [
        'payment_gateways' => 'monetization_payment_gateways',
        'currencies' => 'monetization_currencies',
        'tax_rates' => 'monetization_tax_rates',
        'price_plans' => 'monetization_price_plans',
        'promo_codes' => 'monetization_promo_codes',
        'orders' => 'monetization_orders',
    ];

    public function up(): void
    {
        if (Schema::hasTable('orders') && !Schema::hasTable('monetization_orders')) {
            $this->dropOrderForeignKeys('orders');
        }

        foreach ($this->tableMap as $legacy => $prefixed) {
            if (Schema::hasTable($legacy) && !Schema::hasTable($prefixed)) {
                Schema::rename($legacy, $prefixed);
            }
        }

        if (Schema::hasTable('monetization_orders')) {
            $this->addOrderForeignKeys('monetization_orders');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('monetization_orders') && !Schema::hasTable('orders')) {
            $this->dropOrderForeignKeys('monetization_orders');
        }

        foreach (array_reverse($this->tableMap, true) as $legacy => $prefixed) {
            if (Schema::hasTable($prefixed) && !Schema::hasTable($legacy)) {
                Schema::rename($prefixed, $legacy);
            }
        }

        if (Schema::hasTable('orders')) {
            $this->addOrderForeignKeys('orders');
        }
    }

    private function dropOrderForeignKeys(string $table): void
    {
        foreach (['user_id', 'gateway_id', 'price_plan_id', 'promo_code_id', 'tax_rate_id'] as $column) {
            $constraintNames = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->pluck('CONSTRAINT_NAME')
                ->filter()
                ->values()
                ->all();

            foreach ($constraintNames as $constraint) {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                    str_replace('`', '``', $table),
                    str_replace('`', '``', (string) $constraint)
                ));
            }
        }
    }

    private function addOrderForeignKeys(string $table): void
    {
        $refs = [
            'user_id' => 'users',
            'gateway_id' => str_starts_with($table, 'monetization_') ? 'monetization_payment_gateways' : 'payment_gateways',
            'price_plan_id' => str_starts_with($table, 'monetization_') ? 'monetization_price_plans' : 'price_plans',
            'promo_code_id' => str_starts_with($table, 'monetization_') ? 'monetization_promo_codes' : 'promo_codes',
            'tax_rate_id' => str_starts_with($table, 'monetization_') ? 'monetization_tax_rates' : 'tax_rates',
        ];

        Schema::table($table, function (Blueprint $blueprint) use ($table, $refs) {
            foreach ($refs as $column => $refTable) {
                if (!Schema::hasColumn($table, $column) || !Schema::hasTable($refTable)) {
                    continue;
                }

                if ($this->hasForeignKeyOnColumn($table, $column)) {
                    continue;
                }

                $constraintName = sprintf('%s_%s_foreign', $table, $column);
                $blueprint->foreign($column, $constraintName)
                    ->references('id')
                    ->on($refTable)
                    ->nullOnDelete();
            }
        });
    }

    private function hasForeignKeyOnColumn(string $table, string $column): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};

