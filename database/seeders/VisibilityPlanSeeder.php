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
                'name' => 'Pacote 500',
                'description' => '500 só tem direito a 1 usuário fora do dono da Loja. Tem visibilidade dos Relatórios das vendas feitas no sistema. Gestão de produtos/Stock/Pedidos. Importar Stock.',
                'price' => 500.00,
                'duration_days' => 30,
                'position_boost' => 5,
                'is_featured_badge' => false,
            ],
            [
                'name' => 'Pacote 1000',
                'description' => 'Pacote de 1000 tem direito a todos os pontos do pacote de 500 mais as opções de: 2 Funcionários fora do dono da Loja. Importar stock. Organizar a Loja por filtro de categorias/seções. Opção de queima de stock para 10/dia produtos.',
                'price' => 1000.00,
                'duration_days' => 30,
                'position_boost' => 10,
                'is_featured_badge' => true,
            ],
            [
                'name' => 'Pacote 2000',
                'description' => 'Pacote 2000 tem todos os direitos do Pacote 1 e 2. Queima de stock de 20 produtos por dia. Posição privilegiada nas pesquisas dos produtos no Sistema e na página inicial do Sistema posição 2. 5 Funcionários permitidos. Acesso a personalizar o perfil.',
                'price' => 2000.00,
                'duration_days' => 30,
                'position_boost' => 20,
                'is_featured_badge' => true,
            ],
            [
                'name' => 'Pacote 15000',
                'description' => 'Pacote 15000 todos os direitos dos pacotes acima mas: Sistema POS incluído. Funcionários ilimitados. Queima de stock de mais de 50 produtos por dia. Posição privilegiada no Sistema o seja a Loja e os produtos sempre ficam em primeira posição no destaque em tudo. Acesso ao Sistema Scan and Go que a partir de agora só fica disponível para esse pacote e todas as atualizações do sistema irá receber em tempo real.',
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
