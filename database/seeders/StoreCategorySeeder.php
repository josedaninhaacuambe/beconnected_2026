<?php

namespace Database\Seeders;

use App\Models\StoreCategory;
use Illuminate\Database\Seeder;

class StoreCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Supermercado', 'slug' => 'supermercado', 'icon' => 'shopping-cart'],
            ['name' => 'Mercearia / Conveniência', 'slug' => 'mercearia', 'icon' => 'store'],
            ['name' => 'Ferragem & Construção', 'slug' => 'ferragem', 'icon' => 'hammer'],
            ['name' => 'Roupa & Moda', 'slug' => 'roupa-moda', 'icon' => 'shirt'],
            ['name' => 'Boutique & Acessórios', 'slug' => 'boutique', 'icon' => 'gem'],
            ['name' => 'Electrónica & Tecnologia', 'slug' => 'electronica', 'icon' => 'smartphone'],
            ['name' => 'Farmácia & Saúde', 'slug' => 'farmacia', 'icon' => 'pills'],
            ['name' => 'Padaria & Pastelaria', 'slug' => 'padaria', 'icon' => 'coffee'],
            ['name' => 'Restauração & Comida', 'slug' => 'restauracao', 'icon' => 'utensils'],
            ['name' => 'Livraria & Papelaria', 'slug' => 'livraria', 'icon' => 'book'],
            ['name' => 'Calçado', 'slug' => 'calcado', 'icon' => 'footprints'],
            ['name' => 'Móveis & Decoração', 'slug' => 'moveis', 'icon' => 'sofa'],
            ['name' => 'Automóvel & Moto', 'slug' => 'automovel', 'icon' => 'car'],
            ['name' => 'Beleza & Cosméticos', 'slug' => 'beleza', 'icon' => 'sparkles'],
            ['name' => 'Desporto & Fitness', 'slug' => 'desporto', 'icon' => 'dumbbell'],
            ['name' => 'Brinquedos & Infantil', 'slug' => 'brinquedos', 'icon' => 'baby'],
            ['name' => 'Animais & Pet Shop', 'slug' => 'pet-shop', 'icon' => 'paw-print'],
            ['name' => 'Agricultura & Pecuária', 'slug' => 'agricultura', 'icon' => 'leaf'],
            ['name' => 'Material Eléctrico', 'slug' => 'electrico', 'icon' => 'zap'],
            ['name' => 'Outros', 'slug' => 'outros', 'icon' => 'package'],
        ];

        foreach ($categories as $category) {
            StoreCategory::create($category);
        }
    }
}
