<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\GradePermissionSeeder;
use Database\Seeders\SemanticTaxonomySeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        if (User::query()->count() === 0) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->call([
            PlanSeeder::class,
            GradePermissionSeeder::class,
            SemanticTaxonomySeeder::class,
        ]);
    }
}
