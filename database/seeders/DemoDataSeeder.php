<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Province;
use App\Models\Store;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Localizações ─────────────────────────────────────────────────────
        $maputo    = Province::where('code', 'MPM')->first();
        $sofala    = Province::where('code', 'SOF')->first();
        $nampula   = Province::where('code', 'NAM')->first();

        $maputoCidade  = City::where('name', 'Maputo Cidade')->first()
                      ?? City::where('province_id', $maputo?->id)->first();
        $beira         = City::where('name', 'Beira')->first()
                      ?? City::where('province_id', $sofala?->id)->first();
        $nampulaCidade = City::where('name', 'Nampula Cidade')->first()
                      ?? City::where('province_id', $nampula?->id)->first();

        $bairroMaputo  = Neighborhood::where('city_id', $maputoCidade?->id)->first();
        $bairroBeira   = Neighborhood::where('city_id', $beira?->id)->first();
        $bairroNampula = Neighborhood::where('city_id', $nampulaCidade?->id)->first();

        // ─── Categorias de loja ────────────────────────────────────────────────
        $catSuper    = StoreCategory::where('slug', 'supermercado')->first();
        $catFerr     = StoreCategory::where('slug', 'ferragem')->first();
        $catRoupa    = StoreCategory::where('slug', 'roupa-moda')->first();
        $catElec     = StoreCategory::where('slug', 'electronica')->first();
        $catFarma    = StoreCategory::where('slug', 'farmacia')->first();

        // ─── Categorias de produto ─────────────────────────────────────────────
        $prodCats = $this->createProductCategories();

        // ─── Marcas ────────────────────────────────────────────────────────────
        $brands = $this->createBrands();

        // ─── 5 Donos de loja ──────────────────────────────────────────────────
        $owners = [
            User::firstOrCreate(['email' => 'dono.continente@beconnect.co.mz'], [
                'name'      => 'Carlos Mondlane',
                'phone'     => '258841100001',
                'password'  => bcrypt('Loja@2025'),
                'role'      => 'store_owner',
                'is_active' => true,
                'province_id' => $maputo?->id,
                'city_id'     => $maputoCidade?->id,
            ]),
            User::firstOrCreate(['email' => 'dono.ferreira@beconnect.co.mz'], [
                'name'      => 'António Ferreira',
                'phone'     => '258841100002',
                'password'  => bcrypt('Loja@2025'),
                'role'      => 'store_owner',
                'is_active' => true,
                'province_id' => $maputo?->id,
                'city_id'     => $maputoCidade?->id,
            ]),
            User::firstOrCreate(['email' => 'dono.beira@beconnect.co.mz'], [
                'name'      => 'Fátima Nhantumbo',
                'phone'     => '258841100003',
                'password'  => bcrypt('Loja@2025'),
                'role'      => 'store_owner',
                'is_active' => true,
                'province_id' => $sofala?->id,
                'city_id'     => $beira?->id,
            ]),
            User::firstOrCreate(['email' => 'dono.techstore@beconnect.co.mz'], [
                'name'      => 'Pedro Cossa',
                'phone'     => '258841100004',
                'password'  => bcrypt('Loja@2025'),
                'role'      => 'store_owner',
                'is_active' => true,
                'province_id' => $maputo?->id,
                'city_id'     => $maputoCidade?->id,
            ]),
            User::firstOrCreate(['email' => 'dono.farmacia@beconnect.co.mz'], [
                'name'      => 'Rosa Sitoe',
                'phone'     => '258841100005',
                'password'  => bcrypt('Loja@2025'),
                'role'      => 'store_owner',
                'is_active' => true,
                'province_id' => $nampula?->id,
                'city_id'     => $nampulaCidade?->id,
            ]),
        ];

        // ─── 5 Lojas ───────────────────────────────────────────────────────────
        $stores = [

            // 1. Supermercado Maputo
            $this->createStore($owners[0], [
                'store_category_id' => $catSuper?->id ?? 1,
                'name'              => 'Supermercado Continente Maputo',
                'slug'              => 'continente-maputo',
                'description'       => 'O seu supermercado de confiança em Maputo. Produtos frescos, mercearia, higiene e muito mais com entrega em casa.',
                'phone'             => '258821100001',
                'province_id'       => $maputo?->id,
                'city_id'           => $maputoCidade?->id,
                'neighborhood_id'   => $bairroMaputo?->id,
                'address'           => 'Av. Julius Nyerere, 1234, Sommerschield',
                'rating'            => 4.7,
                'total_reviews'     => 312,
                'is_featured'       => true,
                'visibility_position' => 10,
            ]),

            // 2. Ferragem Maputo
            $this->createStore($owners[1], [
                'store_category_id' => $catFerr?->id ?? 3,
                'name'              => 'Ferragem & Construção Ferreira',
                'slug'              => 'ferragem-ferreira',
                'description'       => 'Material de construção, ferramentas, tintas, canos e tudo para a sua obra. 20 anos de experiência no mercado.',
                'phone'             => '258821100002',
                'province_id'       => $maputo?->id,
                'city_id'           => $maputoCidade?->id,
                'neighborhood_id'   => $bairroMaputo?->id,
                'address'           => 'Av. 24 de Julho, 890, Maputo',
                'rating'            => 4.4,
                'total_reviews'     => 178,
            ]),

            // 3. Boutique Beira
            $this->createStore($owners[2], [
                'store_category_id' => $catRoupa?->id ?? 4,
                'name'              => 'Boutique Fátima Fashion',
                'slug'              => 'fatima-fashion-beira',
                'description'       => 'Roupa feminina e masculina de última moda. Capulanas, vestidos, t-shirts e acessórios. Enviamos para todo o país.',
                'phone'             => '258821100003',
                'province_id'       => $sofala?->id,
                'city_id'           => $beira?->id,
                'neighborhood_id'   => $bairroBeira?->id,
                'address'           => 'Rua Correia de Brito, 45, Ponta-Gea, Beira',
                'rating'            => 4.6,
                'total_reviews'     => 89,
            ]),

            // 4. Tech Store Maputo
            $this->createStore($owners[3], [
                'store_category_id' => $catElec?->id ?? 6,
                'name'              => 'TechStore Moçambique',
                'slug'              => 'techstore-mocambique',
                'description'       => 'Telemóveis, computadores, acessórios e electrónica. Marcas originais com garantia. Assistência técnica disponível.',
                'phone'             => '258821100004',
                'province_id'       => $maputo?->id,
                'city_id'           => $maputoCidade?->id,
                'neighborhood_id'   => $bairroMaputo?->id,
                'address'           => 'Shopping Maputo, Loja 23, Av. Vladimir Lenine',
                'rating'            => 4.8,
                'total_reviews'     => 445,
                'is_featured'       => true,
                'visibility_position' => 8,
            ]),

            // 5. Farmácia Nampula
            $this->createStore($owners[4], [
                'store_category_id' => $catFarma?->id ?? 7,
                'name'              => 'Farmácia Saúde Total Nampula',
                'slug'              => 'saude-total-nampula',
                'description'       => 'Medicamentos, suplementos, produtos de higiene e beleza. Farmacêuticos especializados ao seu serviço.',
                'phone'             => '258821100005',
                'province_id'       => $nampula?->id,
                'city_id'           => $nampulaCidade?->id,
                'neighborhood_id'   => $bairroNampula?->id,
                'address'           => 'Av. Eduardo Mondlane, 567, Nampula Cidade',
                'rating'            => 4.5,
                'total_reviews'     => 201,
            ]),
        ];

        // ─── Produtos por loja ─────────────────────────────────────────────────

        // 1. Supermercado
        $this->createProducts($stores[0], $prodCats, $brands, [
            ['name' => 'Arroz Carolino 5kg',         'price' => 580,   'stock' => 200, 'cat' => 'alimentar', 'brand' => 'Vitariso',  'sku' => 'ARR-001', 'desc' => 'Arroz carolino de grão longo, ideal para pratos do quotidiano.'],
            ['name' => 'Óleo Girassol 1L',            'price' => 185,   'stock' => 150, 'cat' => 'alimentar', 'brand' => 'Girasol',   'sku' => 'OLE-001', 'desc' => 'Óleo de girassol refinado, rico em vitamina E.'],
            ['name' => 'Leite UHT Integral 1L',       'price' => 120,   'stock' => 300, 'cat' => 'alimentar', 'brand' => 'Parmalat',  'sku' => 'LEI-001', 'desc' => 'Leite integral UHT, longa duração.'],
            ['name' => 'Sabão em Pó OMO 1kg',         'price' => 220,   'stock' => 180, 'cat' => 'higiene',   'brand' => 'Unilever',  'sku' => 'SAB-001', 'desc' => 'Detergente em pó para roupa branca e colorida.'],
            ['name' => 'Açúcar Branco 2kg',           'price' => 230,   'stock' => 250, 'cat' => 'alimentar', 'brand' => 'Maragra',   'sku' => 'ACU-001', 'desc' => 'Açúcar branco refinado produzido em Moçambique.'],
            ['name' => 'Massa Esparguete 500g',        'price' => 95,    'stock' => 400, 'cat' => 'alimentar', 'brand' => 'Moamba',    'sku' => 'MAS-001', 'desc' => 'Massa de esparguete de qualidade premium.'],
            ['name' => 'Frango Inteiro Congelado 1kg', 'price' => 395,   'stock' => 80,  'cat' => 'carnes',   'brand' => 'Chicken',   'sku' => 'FRG-001', 'desc' => 'Frango inteiro congelado, criação nacional.'],
            ['name' => 'Cerveja 2M 330ml (pack 6)',    'price' => 540,   'stock' => 120, 'cat' => 'bebidas',  'brand' => '2M',        'sku' => 'CER-001', 'desc' => 'Pack 6 latas da cerveja nacional 2M.'],
        ]);

        // 2. Ferragem
        $this->createProducts($stores[1], $prodCats, $brands, [
            ['name' => 'Cimento 50kg (saco)',          'price' => 890,   'stock' => 500, 'cat' => 'construcao', 'brand' => 'Cimpor',   'sku' => 'CIM-001', 'desc' => 'Cimento Portland resistente, ideal para construção geral.'],
            ['name' => 'Tinta Branca Interior 20L',    'price' => 2450,  'stock' => 60,  'cat' => 'construcao', 'brand' => 'Robbialac','sku' => 'TNT-001', 'desc' => 'Tinta plástica lavável para paredes interiores.'],
            ['name' => 'Martelo Carpinteiro 500g',     'price' => 380,   'stock' => 45,  'cat' => 'ferramentas','brand' => 'Stanley',  'sku' => 'MAR-001', 'desc' => 'Martelo de carpinteiro com cabo de borracha anti-vibração.'],
            ['name' => 'Parafuso Chipboard 4x40 (cx)', 'price' => 145,   'stock' => 200, 'cat' => 'ferramentas','brand' => 'Fix',      'sku' => 'PAR-001', 'desc' => 'Caixa 200 parafusos chipboard zincados 4x40mm.'],
            ['name' => 'Tubo PVC 110mm (barra 6m)',    'price' => 760,   'stock' => 90,  'cat' => 'construcao', 'brand' => 'Tigre',    'sku' => 'TUB-001', 'desc' => 'Tubo PVC rígido para esgotos, cor cinzento.'],
            ['name' => 'Interruptor Simples',           'price' => 95,    'stock' => 300, 'cat' => 'electrico',  'brand' => 'Legrand',  'sku' => 'INT-001', 'desc' => 'Interruptor de embutir para instalações eléctricas.'],
            ['name' => 'Chave de Fendas Set 6pcs',     'price' => 320,   'stock' => 75,  'cat' => 'ferramentas','brand' => 'Stanley',  'sku' => 'CHV-001', 'desc' => 'Conjunto 6 chaves de fendas com cabo ergonómico.'],
        ]);

        // 3. Boutique Moda
        $this->createProducts($stores[2], $prodCats, $brands, [
            ['name' => 'Capulana Bordada Tradicional', 'price' => 450,   'stock' => 80,  'cat' => 'roupa', 'brand' => 'AfrikaWear', 'sku' => 'CAP-001', 'desc' => 'Capulana com bordados tradicionais moçambicanos, 2 metros.'],
            ['name' => 'Vestido Verão Floral M',       'price' => 1200,  'stock' => 35,  'cat' => 'roupa', 'brand' => 'Zara',      'sku' => 'VES-001', 'desc' => 'Vestido verão em tecido leve com padrão floral.'],
            ['name' => 'T-Shirt Beconnect L',          'price' => 550,   'stock' => 100, 'cat' => 'roupa', 'brand' => 'AfrikaWear','sku' => 'TSH-001', 'desc' => 'T-shirt 100% algodão, estampa exclusiva Beconnect.'],
            ['name' => 'Calças Jeans Skinny 38',       'price' => 1850,  'stock' => 25,  'cat' => 'roupa', 'brand' => "Levi's",   'sku' => 'CAL-001', 'desc' => 'Calças de ganga skinny fit, lavagem escura.'],
            ['name' => 'Sandálias Femininas 38',       'price' => 980,   'stock' => 40,  'cat' => 'calcado','brand' => 'Azaleia',  'sku' => 'SAN-001', 'desc' => 'Sandálias femininas confortáveis para uso diário.'],
            ['name' => 'Bolsa Mão Couro PU',           'price' => 2200,  'stock' => 20,  'cat' => 'acessorios','brand' => 'AfrikaWear','sku' => 'BOL-001', 'desc' => 'Bolsa de mão em couro sintético, várias cores.'],
        ]);

        // 4. Tech Store
        $this->createProducts($stores[3], $prodCats, $brands, [
            ['name' => 'Samsung Galaxy A55 5G 256GB',  'price' => 29990, 'stock' => 15,  'cat' => 'telemoveis', 'brand' => 'Samsung',  'sku' => 'SAM-A55', 'desc' => 'Ecrã Super AMOLED 6.6", câmara 50MP, bateria 5000mAh, Android 14.'],
            ['name' => 'iPhone 15 128GB Preto',        'price' => 68000, 'stock' => 8,   'cat' => 'telemoveis', 'brand' => 'Apple',    'sku' => 'APL-15',  'desc' => 'iPhone 15 com chip A16 Bionic, câmara 48MP, USB-C.'],
            ['name' => 'Laptop HP 15 Intel i5 8GB',    'price' => 52000, 'stock' => 10,  'cat' => 'computadores','brand' => 'HP',      'sku' => 'HP-15',   'desc' => 'Laptop HP 15.6" Full HD, Intel i5 12ª gen, 8GB RAM, SSD 512GB.'],
            ['name' => 'Auscultadores Sony WH-1000XM5','price' => 18500, 'stock' => 12,  'cat' => 'audio',      'brand' => 'Sony',     'sku' => 'SON-XM5', 'desc' => 'Auscultadores over-ear com cancelamento de ruído líder da indústria.'],
            ['name' => 'Carregador USB-C 65W GaN',     'price' => 1850,  'stock' => 50,  'cat' => 'acessorios', 'brand' => 'Anker',    'sku' => 'ANK-65W', 'desc' => 'Carregador compacto GaN 65W, carrega laptop, tablet e telemóvel.'],
            ['name' => 'Power Bank 20000mAh',          'price' => 2400,  'stock' => 35,  'cat' => 'acessorios', 'brand' => 'Xiaomi',   'sku' => 'XMI-20K', 'desc' => 'Bateria externa 20000mAh, duas saídas USB-A + USB-C, carga rápida.'],
            ['name' => 'Rato Sem Fios Logitech M185',  'price' => 1200,  'stock' => 40,  'cat' => 'computadores','brand' => 'Logitech','sku' => 'LOG-M185','desc' => 'Rato sem fios 2.4GHz, receptor nano USB, até 12 meses de bateria.'],
            ['name' => 'Pen USB 3.0 SanDisk 64GB',     'price' => 680,   'stock' => 80,  'cat' => 'acessorios', 'brand' => 'SanDisk',  'sku' => 'SND-64G', 'desc' => 'Pen USB 3.0 com velocidade de leitura até 130MB/s.'],
        ]);

        // 5. Farmácia
        $this->createProducts($stores[4], $prodCats, $brands, [
            ['name' => 'Paracetamol 500mg (20 comp.)', 'price' => 85,    'stock' => 500, 'cat' => 'medicamentos', 'brand' => 'Medinfar','sku' => 'PAR-500', 'desc' => 'Analgésico e antipirético. Alívio de dores e febre.'],
            ['name' => 'Ibuprofeno 400mg (20 comp.)',   'price' => 125,   'stock' => 350, 'cat' => 'medicamentos', 'brand' => 'Bayer',   'sku' => 'IBU-400', 'desc' => 'Anti-inflamatório não esteroide para dores e febre.'],
            ['name' => 'Vitamina C 1000mg (30 comp.)', 'price' => 320,   'stock' => 200, 'cat' => 'suplementos',  'brand' => 'Vitacid', 'sku' => 'VIT-C1G', 'desc' => 'Vitamina C efervescente para reforço do sistema imunitário.'],
            ['name' => 'Álcool 70° 500ml',             'price' => 180,   'stock' => 300, 'cat' => 'higiene',      'brand' => 'Medinfar','sku' => 'ALC-70',  'desc' => 'Álcool etílico 70° para desinfecção de pele e superfícies.'],
            ['name' => 'Soro Fisiológico 500ml',       'price' => 95,    'stock' => 150, 'cat' => 'medicamentos', 'brand' => 'Braun',   'sku' => 'SOR-500', 'desc' => 'Solução salina 0.9% para lavagem nasal e feridas.'],
            ['name' => 'Protetor Solar FPS50 200ml',   'price' => 580,   'stock' => 120, 'cat' => 'beleza',       'brand' => 'Nivea',   'sku' => 'SOL-50',  'desc' => 'Protetor solar FPS50 de amplo espectro, resistente à água.'],
            ['name' => 'Fraldas Pampers Tam.3 (40un)', 'price' => 1650,  'stock' => 80,  'cat' => 'bebe',         'brand' => 'Pampers', 'sku' => 'PAM-T3',  'desc' => 'Fraldas Pampers Active Baby tamanho 3 (6-10kg), pack 40 unidades.'],
        ]);

        $this->command->info('✅ 5 lojas e ' . Product::count() . ' produtos de demonstração criados!');
        $this->command->info('');
        $this->command->table(
            ['Loja', 'Dono', 'Email', 'Password'],
            [
                ['Continente Maputo',    'Carlos Mondlane',  'dono.continente@beconnect.co.mz', 'Loja@2025'],
                ['Ferragem Ferreira',    'António Ferreira', 'dono.ferreira@beconnect.co.mz',   'Loja@2025'],
                ['Boutique Fátima',      'Fátima Nhantumbo', 'dono.beira@beconnect.co.mz',      'Loja@2025'],
                ['TechStore',            'Pedro Cossa',      'dono.techstore@beconnect.co.mz',  'Loja@2025'],
                ['Farmácia Saúde Total', 'Rosa Sitoe',       'dono.farmacia@beconnect.co.mz',   'Loja@2025'],
            ]
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createStore(User $owner, array $data): Store
    {
        return Store::firstOrCreate(
            ['slug' => $data['slug']],
            array_merge($data, [
                'user_id'                   => $owner->id,
                'status'                    => 'active',
                'accepts_delivery'          => true,
                'estimated_delivery_minutes'=> 60,
            ])
        );
    }

    private function createProducts(Store $store, array $cats, array $brands, array $products): void
    {
        foreach ($products as $p) {
            $catModel   = $cats[$p['cat']] ?? $cats['outros'];
            $brandModel = $brands[$p['brand']] ?? null;

            $slug = Str::slug($p['name']);

            $product = Product::firstOrCreate(
                ['store_id' => $store->id, 'slug' => $slug],
                [
                    'store_id'            => $store->id,
                    'product_category_id' => $catModel->id,
                    'brand_id'            => $brandModel?->id,
                    'name'                => $p['name'],
                    'slug'                => $slug,
                    'description'         => $p['desc'],
                    'price'               => $p['price'],
                    'sku'                 => $p['sku'],
                    'is_active'           => true,
                    'is_featured'         => in_array($p['sku'], ['SAM-A55', 'ARR-001', 'CAP-001', 'CIM-001', 'PAR-500']),
                    'images'              => [],
                ]
            );

            // Stock
            DB::table('product_stock')->upsert(
                [['product_id' => $product->id, 'quantity' => $p['stock'], 'minimum_stock' => 5, 'unit' => 'unidade']],
                ['product_id'],
                ['quantity']
            );
        }
    }

    private function createProductCategories(): array
    {
        $defs = [
            'alimentar'    => 'Alimentar',
            'bebidas'      => 'Bebidas',
            'higiene'      => 'Higiene & Limpeza',
            'carnes'       => 'Carnes & Peixe',
            'construcao'   => 'Construção & Obras',
            'ferramentas'  => 'Ferramentas',
            'electrico'    => 'Material Eléctrico',
            'roupa'        => 'Roupa & Vestuário',
            'calcado'      => 'Calçado',
            'acessorios'   => 'Acessórios',
            'telemoveis'   => 'Telemóveis & Tablets',
            'computadores' => 'Computadores & Periféricos',
            'audio'        => 'Áudio & Vídeo',
            'medicamentos' => 'Medicamentos',
            'suplementos'  => 'Suplementos & Vitaminas',
            'beleza'       => 'Beleza & Cosméticos',
            'bebe'         => 'Bebé & Infantil',
            'outros'       => 'Outros',
        ];

        $result = [];
        foreach ($defs as $key => $name) {
            $result[$key] = ProductCategory::firstOrCreate(
                ['slug' => $key],
                ['name' => $name, 'slug' => $key, 'is_active' => true]
            );
        }
        return $result;
    }

    private function createBrands(): array
    {
        $names = [
            'Vitariso','Girasol','Parmalat','Unilever','Maragra','Moamba','Chicken','2M',
            'Cimpor','Robbialac','Stanley','Fix','Tigre','Legrand',
            'AfrikaWear','Zara',"Levi's",'Azaleia',
            'Samsung','Apple','HP','Sony','Anker','Xiaomi','Logitech','SanDisk',
            'Medinfar','Bayer','Vitacid','Braun','Nivea','Pampers',
        ];

        $result = [];
        foreach ($names as $name) {
            $result[$name] = Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'slug' => Str::slug($name)]
            );
        }
        return $result;
    }
}
