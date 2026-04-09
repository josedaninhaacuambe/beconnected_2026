<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Abstracção de pesquisa de produtos.
 * Usa Meilisearch quando disponível, com fallback automático para MySQL FULLTEXT.
 */
class ProductSearchService
{
    private string $meiliHost;
    private string $meiliKey;
    private bool   $meiliEnabled;

    public function __construct()
    {
        $this->meiliHost    = config('services.meilisearch.host', '');
        $this->meiliKey     = config('services.meilisearch.key', '');
        $this->meiliEnabled = !empty($this->meiliHost);
    }

    /**
     * Pesquisa produtos com os filtros do request.
     * Tenta Meilisearch primeiro; cai para MySQL se indisponível.
     */
    public function search(Request $request): array
    {
        if ($this->meiliEnabled && $this->meilisearchOnline()) {
            try {
                return $this->searchViaMeilisearch($request);
            } catch (\Throwable $e) {
                Log::warning('Meilisearch falhou, fallback para MySQL', ['error' => $e->getMessage()]);
            }
        }

        return $this->searchViaMysql($request);
    }

    // ─── Meilisearch ─────────────────────────────────────────────────────────

    private function searchViaMeilisearch(Request $request): array
    {
        $q      = $request->q ?? '';
        $page   = (int) $request->get('page', 1);
        $limit  = 24;
        $offset = ($page - 1) * $limit;

        $filters = [];

        if ($request->category_id) {
            $filters[] = "category_id = {$request->category_id}";
        }
        if ($request->brand_id || $request->brand) {
            // brand pode vir como nome ou ID
            if ($request->brand_id) {
                $filters[] = "brand_id = {$request->brand_id}";
            }
        }
        if ($request->province_id) {
            $filters[] = "store_province_id = {$request->province_id}";
        }
        if ($request->city_id) {
            $filters[] = "store_city_id = {$request->city_id}";
        }
        // Mostrar apenas produtos com stock > 0 por omissão
        $filters[] = 'stock_quantity > 0';
        // Excluir produtos apenas disponíveis no POS, se o campo existir no schema
        if (Product::hasPosOnlyColumn()) {
            $filters[] = 'pos_only = false';
        }

        $payload = [
            'q'                 => $q,
            'limit'             => $limit,
            'offset'            => $offset,
            'filter'            => implode(' AND ', $filters) ?: null,
            'sort'              => ['has_visibility:desc', 'is_featured:desc', 'total_sold:desc'],
            'attributesToHighlight' => [],
        ];

        $response = Http::withToken($this->meiliKey)
            ->post("{$this->meiliHost}/indexes/products/search", array_filter($payload))
            ->throw()
            ->json();

        $hits  = $response['hits'] ?? [];
        $total = $response['estimatedTotalHits'] ?? count($hits);

        return [
            'data'   => $hits,
            'meta'   => [
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $limit),
                'total'        => $total,
                'engine'       => 'meilisearch',
            ],
        ];
    }

    // ─── MySQL (fallback) ─────────────────────────────────────────────────────

    public function searchViaMysql(Request $request): array
    {
        $query = Product::with(['store.province', 'store.city', 'brand', 'category', 'stock'])
            ->where('is_active', true)
            ->excludePosOnly()
            ->whereHas('store', fn($q) => $q->where('status', 'active'))
            ->whereHas('store', fn($q) => $q->whereHas('visibilityPurchases', fn($vp) => $vp->where('expires_at', '>', now())));

        if ($request->q) {
            $term = $request->q;
            $like = '%' . addslashes($term) . '%';
            $query->where(function ($q) use ($term, $like) {
                if (mb_strlen($term) < 3) {
                    $q->where('name', 'like', $like)
                      ->orWhere('model', 'like', $like)
                      ->orWhere('sku', 'like', $like)
                      ->orWhereHas('brand', fn($b) => $b->where('name', 'like', $like))
                      ->orWhereHas('category', fn($c) => $c->where('name', 'like', $like))
                      ->orWhereHas('store', fn($s) => $s->where('name', 'like', $like));
                } else {
                    $ftTerm = implode(' ', array_map(fn($w) => $w . '*', preg_split('/\s+/', trim($term))));
                    $q->whereRaw('MATCH(name, model) AGAINST (? IN BOOLEAN MODE)', [$ftTerm])
                      ->orWhere('name', 'like', $like)
                      ->orWhere('model', 'like', $like)
                      ->orWhere('sku', 'like', $like)
                      ->orWhereHas('brand', fn($b) => $b->where('name', 'like', $like))
                      ->orWhereHas('store', fn($s) => $s->where('name', 'like', $like));
                }
            });
        }

        if ($request->brand) {
            $query->whereHas('brand', fn($q) => $q->where('name', 'like', '%' . $request->brand . '%'));
        }
        if ($request->category_id) {
            $query->where('product_category_id', $request->category_id);
        }
        if ($request->province_id) {
            $query->whereHas('store', fn($q) => $q->where('province_id', $request->province_id));
        }
        if ($request->city_id) {
            $query->whereHas('store', fn($q) => $q->where('city_id', $request->city_id));
        }

        // Geo-pesquisa
        if ($request->lat && $request->lng) {
            $lat    = (float) $request->lat;
            $lng    = (float) $request->lng;
            $radius = (float) ($request->radius ?? 10);
            $query->whereHas('store', function ($q) use ($lat, $lng, $radius) {
                $q->whereNotNull('latitude')->whereNotNull('longitude')
                  ->whereRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?",
                      [$lat, $lng, $lat, $radius]);
            });
        }

        $query->orderByRaw('
            CASE WHEN EXISTS (
                SELECT 1 FROM store_visibility_purchases svp
                WHERE svp.store_id = products.store_id AND svp.expires_at > NOW()
            ) THEN 0 ELSE 1 END
        ')->orderByDesc('products.is_featured');

        $paginated = $query->paginate(24);

        $lat = $request->lat ? (float) $request->lat : null;
        $lng = $request->lng ? (float) $request->lng : null;

        $items = $paginated->getCollection()->map(function ($p) use ($lat, $lng) {
            $arr = $p->toPublicArray();
            if ($lat && $lng && $p->store->latitude && $p->store->longitude) {
                $d = 6371 * acos(
                    cos(deg2rad($lat)) * cos(deg2rad($p->store->latitude))
                    * cos(deg2rad($p->store->longitude) - deg2rad($lng))
                    + sin(deg2rad($lat)) * sin(deg2rad($p->store->latitude))
                );
                $arr['store']['distance_km'] = round($d, 1);
            }
            return $arr;
        });

        if ($lat && $lng) {
            $items = $items->sortBy('store.distance_km')->values();
        }

        return [
            'data' => $items->values()->toArray(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
                'engine'       => 'mysql',
            ],
        ];
    }

    // ─── Utilitários ─────────────────────────────────────────────────────────

    private function meilisearchOnline(): bool
    {
        try {
            $response = Http::timeout(2)->get("{$this->meiliHost}/health");
            return $response->successful() && ($response->json('status') === 'available');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Configurar o índice Meilisearch com os atributos correctos.
     * Chamar uma vez: php artisan tinker --execute="app(ProductSearchService::class)->configureIndex();"
     */
    public function configureIndex(): void
    {
        if (!$this->meiliEnabled) {
            return;
        }

        Http::withToken($this->meiliKey)->patch("{$this->meiliHost}/indexes/products/settings", [
            'searchableAttributes' => ['name', 'model', 'sku', 'barcode', 'brand_name', 'category_name', 'store_name', 'description'],
            'filterableAttributes' => ['store_city_id', 'store_province_id', 'category_id', 'brand_id', 'has_visibility', 'stock_quantity', 'is_featured'],
            'sortableAttributes'   => ['total_sold', 'rating', 'created_at_ts'],
            'rankingRules'         => ['words', 'typo', 'attribute', 'sort', 'exactness'],
            'typoTolerance'        => ['enabled' => true, 'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8]],
        ]);
    }
}
