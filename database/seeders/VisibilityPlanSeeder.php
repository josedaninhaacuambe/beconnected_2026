<?php

namespace Database\Seeders;

use App\Models\VisibilityPlan;
use Illuminate\Database\Seeder;

class VisibilityPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Básico',
                'description' => 'Apareça nas primeiras posições durante 7 dias.',
                'price' => 500.00,
                'duration_days' => 7,
                'position_boost' => 10,
                'is_featured_badge' => false,
            ],
            [
                'name' => 'Standard',
                'description' => 'Destaque a sua loja durante 15 dias com badge de destaque.',
                'price' => 1000.00,
                'duration_days' => 15,
                'position_boost' => 25,
                'is_featured_badge' => true,
            ],
            [
                'name' => 'Premium',
                'description' => 'Máxima visibilidade durante 30 dias. Topo da lista garantido.',
                'price' => 2000.00,
                'duration_days' => 30,
                'position_boost' => 50,
                'is_featured_badge' => true,
            ],
            [
                'name' => 'Anual',
                'description' => 'Visibilidade máxima o ano inteiro. Melhor custo-benefício.',
                'price' => 15000.00,
                'duration_days' => 365,
                'position_boost' => 100,
                'is_featured_badge' => true,
            ],
        ];

        foreach ($plans as $plan) {
            VisibilityPlan::create($plan);
        }
    }
}
