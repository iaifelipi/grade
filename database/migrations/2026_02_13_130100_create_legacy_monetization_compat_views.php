<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string,string>
     */
    private array $viewMap = [
        'payment_gateways' => 'monetization_payment_gateways',
        'currencies' => 'monetization_currencies',
        'tax_rates' => 'monetization_tax_rates',
        'price_plans' => 'monetization_price_plans',
        'promo_codes' => 'monetization_promo_codes',
        'orders' => 'monetization_orders',
    ];

    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        foreach ($this->viewMap as $legacyName => $prefixedTable) {
            $legacyType = $this->tableType($legacyName);
            $prefixedType = $this->tableType($prefixedTable);

            if ($prefixedType !== 'BASE TABLE') {
                continue;
            }

            if ($legacyType === 'BASE TABLE') {
                // Safety: never replace a base table with a view.
                continue;
            }

            DB::statement(sprintf(
                'DROP VIEW IF EXISTS `%s`',
                str_replace('`', '``', $legacyName)
            ));

            DB::statement(sprintf(
                'CREATE VIEW `%s` AS SELECT * FROM `%s`',
                str_replace('`', '``', $legacyName),
                str_replace('`', '``', $prefixedTable)
            ));
        }
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        foreach (array_keys($this->viewMap) as $legacyName) {
            if ($this->tableType($legacyName) !== 'VIEW') {
                continue;
            }

            DB::statement(sprintf(
                'DROP VIEW IF EXISTS `%s`',
                str_replace('`', '``', $legacyName)
            ));
        }
    }

    private function tableType(string $name): ?string
    {
        return DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $name)
            ->value('TABLE_TYPE');
    }
};
