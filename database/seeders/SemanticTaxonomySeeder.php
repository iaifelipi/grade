<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SemanticTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSimple('semantic_segments', [
            ['name' => 'Geral'],
        ]);

        $this->seedSimple('semantic_niches', [
            ['name' => 'Geral'],
        ]);

        $this->seedSimple('semantic_origins', [
            ['name' => 'Geral'],
        ]);

        $this->seedSimple('semantic_countries', [
            ['name' => 'Brasil'],
        ]);
    }

    private function seedSimple(string $table, array $rows): void
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $hasNormalized = DB::getSchemaBuilder()->hasColumn($table, 'normalized');

        foreach ($rows as $row) {
            if ($hasNormalized && !array_key_exists('normalized', $row)) {
                $row['normalized'] = Str::slug((string) ($row['name'] ?? ''), ' ');
            }
            DB::table($table)->updateOrInsert(
                ['name' => $row['name']],
                $row + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
