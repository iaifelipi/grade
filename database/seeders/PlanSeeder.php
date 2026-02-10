<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'Free',
                'description' => 'Entrada para novos usuários',
                'is_paid' => false,
                'sort_order' => 1,
            ],
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'Plano inicial pago',
                'is_paid' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'description' => 'Plano avançado para times',
                'is_paid' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
