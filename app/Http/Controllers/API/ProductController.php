<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\IndexProductInSearch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStock;
use App\Models\Store;
use App\Services\ProductImageService;
use App\Services\Search\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // Pesquisa global de produtos (SEM PREÇO - preço só é visível dentro da loja)
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'               => 'nullable|string|max:255',
            'brand'           => 'nullable|string',
            'model'           => 'nullable|string',
            'category_id'     => 'nullable|exists:product_categories,id',
            'province_id'     => 'nullable|exists:provinces,id',
            'city_id'         => 'nullable|exists:cities,id',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'lat'             => 'nullable|numeric|between:-90,90',
            'lng'             => 'nullable|numeric|between:-180,180',
            'radius'          => 'nullable|numeric|min:1|max:100',
            'only_visibility' => 'nullable|boolean',
        ]);

        // Delegar ao ProductSearchService (Meilisearch com fallback MySQL)
        $searchService = app(ProductSearchService::class);
        $hasGeo = $request->lat && $request->lng;

        if (!$hasGeo) {
            $cacheKey = 'search_' . md5(serialize($request->only(['q','brand','model','category_id','province_id','city_id','neighborhood_id','page'])));
            $data = Cache::remember($cacheKey, 120, fn() => $searchService->search($request));
            return response()->json($data);
        }

        return response()->json($searchService->search($request));
    }

    private function buildSearchData(Request $request): array
    {
        $query = Product::with(['store.province', 'store.city', 'brand', 'category', 'stock'])
            ->where('is_active', true)
            ->excludePosOnly()
            ->whereHas('store', fn($q) => $q->where('status', 'active'))
            ->whereHas('store', fn($q) => $q->whereHas('visibilityPurchases', fn($vp) => $vp->where('expires_at', '>', now())));

        if ($request->q) {
            $term = $request->q;
            $like = '%' . addslashes($term) . '%';
            // Para termos curtos (< 3 chars) ou que contenham espaços, usa LIKE
            // Para termos mais longos usa FULLTEXT com fallback LIKE
            $query->where(function ($q) use ($term, $like) {
                if (mb_strlen($term) < 3) {
                    $q->where('name', 'like', $like)
                      ->orWhere('model', 'like', $like)
                      ->orWhere('description', 'like', $like)
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
                      ->orWhereHas('category', fn($c) => $c->where('name', 'like', $like))
                      ->orWhereHas('store', fn($s) => $s->where('name', 'like', $like));
                }
            });
        }
        if ($request->brand) {
            $query->whereHas('brand', fn($q) => $q->where('name', 'like', '%' . $request->brand . '%'));
        }
        if ($request->model) {
            // FULLTEXT on model column alone
            $query->whereRaw('MATCH(name, model) AGAINST (? IN BOOLEAN MODE)', [addslashes($request->model) . '*']);
        }
        if ($request->category_id) {
            $query->where('product_category_id', $request->category_id);
        }

        // Filtro por localização geográfica (proximidade)
        if ($request->lat && $request->lng) {
            $lat    = (float) $request->lat;
            $lng    = (float) $request->lng;
            $radius = (float) ($request->radius ?? 10);

            $query->whereHas('store', function ($q) use ($lat, $lng, $radius) {
                $q->where('status', 'active')
                  ->whereNotNull('latitude')
                  ->whereNotNull('longitude')
                  ->whereRaw("
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?)) * sin(radians(latitude))
                    )) <= ?
                  ", [$lat, $lng, $lat, $radius]);
            });
        } else {
            if ($request->province_id) {
                $query->whereHas('store', fn($q) => $q->where('province_id', $request->province_id));
            }
            if ($request->city_id) {
                $query->whereHas('store', fn($q) => $q->where('city_id', $request->city_id));
            }
            if ($request->neighborhood_id) {
                $query->whereHas('store', fn($q) => $q->where('neighborhood_id', $request->neighborhood_id));
            }
        }

        // Lojas com visibilidade activa aparecem primeiro
        $query->orderByRaw('
            CASE WHEN EXISTS (
                SELECT 1 FROM store_visibility_purchases svp
                WHERE svp.store_id = products.store_id
                  AND svp.expires_at > NOW()
            ) THEN 0 ELSE 1 END
        ')->orderByDesc('products.is_featured');

        $products = $query->paginate(24);

        // Calcular distância se near-me activo
        $lat = $request->lat ? (float) $request->lat : null;
        $lng = $request->lng ? (float) $request->lng : null;

        $items = $products->getCollection()->map(function ($p) use ($lat, $lng) {
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

        // Ordenar por distância se near-me activo
        if ($lat && $lng) {
            $items = $items->sortBy('store.distance_km')->values();
        }

        return [
            'data' => $items->values()->toArray(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total(),
            ],
        ];
    }

    // Todos os produtos de lojas com visibilidade (para página de produtos)
    public function allProducts(Request $request): JsonResponse
    {
        return $this->search($request);
    }

    // Produtos de uma loja específica (COM PREÇO)
    public function storeProducts(Request $request, string $storeSlug): JsonResponse
    {
        $store = Store::where('slug', $storeSlug)->where('status', 'active')->firstOrFail();

        // Cache page 1 with no filters (most common hit during stress tests)
        $hasFilters = $request->q || $request->category_id || $request->brand_id
            || $request->min_price || $request->max_price || $request->section_id;
        $page = $request->get('page', 1);
        $sort = $request->get('sort', 'featured');

        if (!$hasFilters) {
            $cacheKey = "store_products_{$storeSlug}_p{$page}_{$sort}";
            $data = Cache::remember($cacheKey, 180, function () use ($store, $request, $sort) {
                return $this->buildStoreProductsData($store, $request, $sort);
            });
            return response()->json($data);
        }

        return response()->json($this->buildStoreProductsData($store, $request, $sort));
    }

    private function buildStoreProductsData($store, Request $request, string $sort): array
    {
        $query = Product::with(['brand', 'category', 'stock'])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->excludePosOnly();

        // Aplicar limitações se não houver visibilidade activa
        // MySQL 8.0 não suporta LIMIT em subquery com IN — buscar IDs primeiro
        if (!$store->hasActiveVisibility()) {
            $maxProducts = $store->getMaxProducts();
            $limitedIdsQuery = DB::table('products')
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->whereNull('deleted_at');
            if (Product::hasAvailabilityColumn()) {
                $limitedIdsQuery->forVirtualStore();
            }
            $limitedIds = $limitedIdsQuery
                ->orderByDesc('is_featured')
                ->orderByDesc('created_at')
                ->limit($maxProducts)
                ->pluck('id')
                ->all();
            $query->whereIn('id', $limitedIds);
        }

        if ($request->q) {
            $term = $request->q;
            $like = '%' . addslashes($term) . '%';
            if (mb_strlen($term) < 3) {
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)->orWhere('model', 'like', $like)->orWhere('sku', 'like', $like);
                });
            } else {
                $ftTerm = implode(' ', array_map(fn($w) => $w . '*', preg_split('/\s+/', trim($term))));
                $query->where(function ($q) use ($ftTerm, $like) {
                    $q->whereRaw('MATCH(name, model) AGAINST (? IN BOOLEAN MODE)', [$ftTerm])
                      ->orWhere('name', 'like', $like)
                      ->orWhere('model', 'like', $like)
                      ->orWhere('sku', 'like', $like);
                });
            }
        }
        if ($request->category_id) {
            $query->where('product_category_id', $request->category_id);
        }
        if ($request->brand_id) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->min_price) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->max_price) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->section_id) {
            $query->where('store_section_id', $request->section_id);
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('rating'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('is_featured')->orderByDesc('total_sold'),
        };

        $paginated = $query->paginate(24);

        $items = collect($paginated->items())->map(fn($p) => [
            'id'            => $p->id,
            'name'          => $p->name,
            'slug'          => $p->slug,
            'price'         => $p->price,
            'compare_price' => $p->compare_price,
            'flash_price'   => $p->flash_price,
            'flash_until'   => $p->flash_until?->toISOString(),
            'images'        => $p->images ?? [],
            'is_featured'   => $p->is_featured,
            'rating'        => $p->rating,
            'total_reviews' => $p->total_reviews,
            'brand'         => $p->brand ? ['id' => $p->brand->id, 'name' => $p->brand->name] : null,
            'category'      => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null,
            'stock'         => $p->stock ? ['quantity' => $p->stock->quantity, 'minimum_stock' => $p->stock->minimum_stock] : null,
        ])->all();

        return [
            'data'          => $items,
            'current_page'  => $paginated->currentPage(),
            'last_page'     => $paginated->lastPage(),
            'total'         => $paginated->total(),
        ];
    }

    // Detalhe de produto (COM PREÇO)
    public function show(string $storeSlug, string $productSlug): JsonResponse
    {
        $store = Store::where('slug', $storeSlug)->where('status', 'active')->firstOrFail();

        $product = Product::with(['brand', 'category', 'stock', 'reviews.user'])
            ->where('store_id', $store->id)
            ->where('slug', $productSlug)
            ->where('is_active', true)
            ->excludePosOnly()
            ->firstOrFail();

        return response()->json($product);
    }

    // --- Gestão de produtos pelo dono da loja ---

    public function myProducts(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $perPage = min((int) $request->get('per_page', 20), 200);

        $products = Product::with(['brand', 'category', 'stock', 'storeSection'])
            ->where('store_id', $store->id)
            ->latest()
            ->paginate($perPage);

        return response()->json($products);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'product_category_id' => 'required|exists:product_categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:255',
            'attributes' => 'nullable|array',
            'initial_stock' => 'required|integer|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:50',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'pos_only' => 'nullable|boolean',
            'availability' => 'nullable|in:virtual_store,pos,both',
            'selling_modes' => 'nullable|array',
            'selling_modes.*' => 'in:weight,unit',
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('products', 'public');
            }
        }

        // Se não foram fornecidas imagens, buscar automaticamente na internet
        if (empty($images)) {
            $brandName = isset($validated['brand_id'])
                ? Brand::find($validated['brand_id'])?->name
                : null;
            $autoImage = (new ProductImageService())->fetchForProduct($validated['name'], $brandName);
            if ($autoImage) {
                $images = [$autoImage];
            }
        }

        $productData = [
            ...$validated,
            'store_id' => $store->id,
            'slug' => Str::slug($validated['name']) . '-' . Str::random(6),
            'images' => $images,
        ];
        if (Product::hasAvailabilityColumn()) {
            $productData['availability'] = $validated['availability'] ?? 'both';
        }
        $productData['selling_modes'] = $validated['selling_modes'] ?? ['unit'];

        $product = Product::create($productData);

        ProductStock::create([
            'product_id' => $product->id,
            'quantity' => $validated['initial_stock'],
            'minimum_stock' => $validated['minimum_stock'] ?? 5,
            'unit' => $validated['unit'] ?? 'unidade',
        ]);

        // Indexar no Meilisearch de forma assíncrona
        IndexProductInSearch::dispatch($product->id)->onQueue('search_index');

        // Invalidar cache POS para que o produto apareça imediatamente no terminal
        Cache::forget("pos_products_{$store->id}");

        return response()->json($product->load(['brand', 'category', 'stock']), 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'description'         => 'nullable|string',
            'price'               => 'sometimes|numeric|min:0',
            'compare_price'       => 'nullable|numeric|min:0',
            'is_active'           => 'sometimes|boolean',
            'model'               => 'nullable|string|max:255',
            'sku'                 => 'nullable|string|max:100',
            'barcode'             => 'nullable|string|max:100',
            'attributes'          => 'nullable|array',
            'brand_id'            => 'nullable|exists:brands,id',
            'product_category_id' => 'nullable|exists:product_categories,id',
            'store_section_id'    => 'nullable|exists:store_sections,id',
            'images'              => 'nullable|array',
            'images.*'            => 'image|max:2048',
            'minimum_stock'       => 'nullable|integer|min:0',
            'pos_only'            => 'nullable|boolean',
            'availability'        => 'nullable|in:virtual_store,pos,both',
            'selling_modes'       => 'nullable|array',
            'selling_modes.*'     => 'in:weight,unit',
        ]);

        // Handle image uploads
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('products', 'public');
            }
            $validated['images'] = array_merge($product->images ?? [], $images);
        }
        unset($validated['minimum_stock']);
        if (!Product::hasAvailabilityColumn()) {
            unset($validated['availability']);
        }

        $product->update($validated);

        // Update minimum stock if provided
        if ($request->has('minimum_stock') && $product->stock) {
            $product->stock->update(['minimum_stock' => $request->minimum_stock]);
        }

        // Re-indexar no Meilisearch
        IndexProductInSearch::dispatch($product->id)->onQueue('search_index');

        // Invalidar cache POS
        Cache::forget("pos_products_{$product->store_id}");

        return response()->json($product->fresh()->load(['brand', 'category', 'stock']));
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $productId = $product->id;
        $product->delete();
        // Remover do índice de pesquisa
        IndexProductInSearch::dispatch($productId, delete: true)->onQueue('search_index');
        return response()->json(['message' => 'Produto removido.']);
    }

    // Actualizar stock
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        $stock = $product->stock;
        $before = $stock->quantity;

        $after = match ($validated['type']) {
            'in' => $before + $validated['quantity'],
            'out' => max(0, $before - $validated['quantity']),
            'adjustment' => $validated['quantity'],
        };

        $stock->update(['quantity' => $after]);

        // Invalidate cart availability cache for this product
        Cache::forget("cart_product_{$product->id}");

        $product->stockMovements()->create([
            'type' => $validated['type'],
            'quantity' => $validated['quantity'],
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => $validated['reason'],
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Stock actualizado.', 'stock' => $stock->fresh()]);
    }

    // Histórico de movimentos de stock
    public function stockMovements(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $movements = $product->stockMovements()
            ->with('user:id,name')
            ->latest()
            ->take(50)
            ->get();

        return response()->json([
            'product' => [
                'id'            => $product->id,
                'name'          => $product->name,
                'sku'           => $product->sku,
                'quantity'      => $product->stock?->quantity ?? 0,
                'minimum_stock' => $product->stock?->minimum_stock ?? 5,
            ],
            'movements' => $movements,
        ]);
    }

    // ─── VIRAL HOOKS ─────────────────────────────────────────────────────────

    /**
     * Flash deals — products with an active flash_until timestamp.
     */
    public function flashDeals(): JsonResponse
    {
        $products = Cache::remember('products_flash', 60, fn () =>
            Product::with(['store', 'stock'])
                ->where('is_active', true)
                ->excludePosOnly()
                ->whereNotNull('flash_price')
                ->whereNotNull('flash_until')
                ->where('flash_until', '>', now())
                ->whereHas('store', fn($q) => $q->where('status', 'active'))
                ->orderBy('flash_until')
                ->get()
                ->map(fn($p) => $this->toHookArray($p))
                ->all()
        );
        return response()->json($products);
    }

    public function trending(): JsonResponse
    {
        $products = Cache::remember('products_trending', 600, fn () =>
            Product::with(['store', 'stock'])
                ->where('is_active', true)
                ->excludePosOnly()
                ->whereHas('store', fn($q) => $q->where('status', 'active'))
                ->orderByDesc('total_sold')
                ->take(8)
                ->get()
                ->map(fn($p) => $this->toHookArray($p))
                ->all()
        );
        return response()->json($products);
    }

    public function discounts(): JsonResponse
    {
        $products = Cache::remember('products_discounts', 300, fn () =>
            Product::with(['store', 'stock'])
                ->where('is_active', true)
                ->excludePosOnly()
                ->whereNotNull('compare_price')
                ->whereColumn('compare_price', '>', 'price')
                ->whereHas('store', fn($q) => $q->where('status', 'active'))
                ->orderByRaw('((compare_price - price) / compare_price) DESC')
                ->take(10)
                ->get()
                ->map(fn($p) => $this->toHookArray($p))
                ->all()
        );
        return response()->json($products);
    }

    // Buscar imagem automática para um produto (preview antes de guardar)
    public function fetchAutoImage(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255', 'brand_id' => 'nullable|exists:brands,id']);
        $brand = $request->brand_id ? Brand::find($request->brand_id)?->name : null;
        $url = (new ProductImageService())->fetchForProduct($request->name, $brand);
        return response()->json(['url' => $url]);
    }

    /**
     * Shared serialiser for viral hook endpoints (includes pricing + stock).
     */
    private function toHookArray(Product $p): array
    {
        return [
            'id'            => $p->id,
            'name'          => $p->name,
            'slug'          => $p->slug,
            'price'         => $p->price,
            'compare_price' => $p->compare_price,
            'flash_price'   => $p->flash_price,
            'flash_until'   => $p->flash_until?->toISOString(),
            'total_sold'    => $p->total_sold ?? 0,
            'rating'        => $p->rating,
            'total_reviews' => $p->total_reviews,
            'images'        => $p->images ?? [],
            'store' => [
                'id'   => $p->store->id,
                'name' => $p->store->name,
                'slug' => $p->store->slug,
                'logo' => $p->store->logo,
            ],
            'stock' => [
                'quantity' => $p->stock?->quantity ?? 0,
            ],
        ];
    }

    // ─── CATEGORIAS / MARCAS ──────────────────────────────────────────────────

    public function categories(): JsonResponse
    {
        return response()->json(ProductCategory::with('children')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->get());
    }

    public function brands(): JsonResponse
    {
        return response()->json(Brand::orderBy('name')->get());
    }

    // Queimar stock (reduzir stock para promover vendas)
    public function burnStock(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $store = $product->store;
        if (!$store->canBurnStock()) {
            return response()->json(['message' => 'Seu pacote não permite queima de stock.'], 403);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $maxBurn = $store->getMaxStockBurnPerDay();
        // Check daily burn limit (simplified, in real app use cache or table)
        $todayBurn = $product->stockMovements()
            ->where('type', 'burn')
            ->whereDate('created_at', today())
            ->sum('quantity');

        if ($todayBurn + $validated['quantity'] > $maxBurn) {
            return response()->json(['message' => "Limite diário de queima: {$maxBurn} unidades."], 422);
        }

        $stock = $product->stock;
        $before = $stock->quantity;
        $after = max(0, $before - $validated['quantity']);

        $stock->update(['quantity' => $after]);

        Cache::forget("cart_product_{$product->id}");

        $product->stockMovements()->create([
            'type' => 'burn',
            'quantity' => $validated['quantity'],
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => 'Queima de stock para promoção',
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Stock queimado com sucesso.', 'stock' => $stock->fresh()]);
    }
}
