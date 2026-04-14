<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreCategory;
use App\Traits\ResolvesOwnerStore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    use ResolvesOwnerStore;
    // ─── Scan & Go: pesquisa produto pelo código de barras ─────────────────
    public function scanBarcode(Request $request, string $slug): JsonResponse
    {
        $store = Store::where('slug', $slug)->where('status', 'active')->firstOrFail();

        if (!$store->canUseScanAndGo()) {
            return response()->json(['message' => 'Scan & Go disponível apenas para pacote 15000.'], 403);
        }

        $barcode = trim($request->query('barcode', ''));
        if (!$barcode) {
            return response()->json(['message' => 'Código de barras obrigatório'], 422);
        }

        $product = $store->products()
            ->with(['stock', 'category'])
            ->where(function ($q) use ($barcode) {
                $q->where('barcode', $barcode)->orWhere('sku', $barcode);
            })
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Produto não encontrado nesta loja'], 404);
        }

        return response()->json($product);
    }

    // ─── Scan & Go: checkout na loja (sem entrega) ─────────────────────────
    public function inStoreCheckout(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:emola,mpesa,cash',
            'payment_phone'  => 'nullable|string|max:20',
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        $store = Store::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $user  = $request->user();

        if (!$store->canUseScanAndGo()) {
            return response()->json(['message' => 'Scan & Go disponível apenas para pacote 15000.'], 403);
        }

        // Verificar que todos os produtos pertencem à loja e têm stock
        $productIds = collect($validated['items'])->pluck('product_id');
        $products   = $store->products()->with('stock')
            ->whereIn('id', $productIds)->where('is_active', true)->get()->keyBy('id');

        foreach ($validated['items'] as $item) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                return response()->json(['message' => 'Produto inválido para esta loja'], 422);
            }
            $stock = $product->stock?->quantity ?? 0;
            if ($stock < $item['quantity']) {
                return response()->json([
                    'message'   => "Stock insuficiente: {$product->name} (disponível: {$stock})",
                    'available' => $stock,
                ], 422);
            }
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $user, $store, $products) {
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $products[$item['product_id']]->price * $item['quantity'];
            }

            // Criar pedido
            $order = \App\Models\Order::create([
                'user_id'          => $user->id,
                'subtotal'         => $subtotal,
                'delivery_fee'     => 0,
                'total'            => $subtotal,
                'status'           => 'confirmed',
                'payment_status'   => $validated['payment_method'] === 'cash' ? 'pending' : 'pending',
                'payment_method'   => $validated['payment_method'],
                'delivery_address' => 'Levantamento em loja — ' . $store->name,
                'province_id'      => $store->province_id,
                'city_id'          => $store->city_id,
                'notes'            => 'Compra presencial via Scan & Go',
                'order_number'     => 'BC-' . strtoupper(uniqid()),
            ]);

            // Sub-pedido da loja
            $storeOrder = $order->storeOrders()->create([
                'store_id'     => $store->id,
                'status'       => 'confirmed',
                'subtotal'     => $subtotal,
                'total_amount' => $subtotal,
                'payment_method' => $validated['payment_method'],
            ]);

            // Itens
            foreach ($validated['items'] as $item) {
                $product = $products[$item['product_id']];
                $storeOrder->items()->create([
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $product->price,
                    'subtotal'     => $product->price * $item['quantity'],
                ]);
                // Reduzir stock
                if ($product->stock) {
                    $product->stock->decrement('quantity', $item['quantity']);
                }
            }

            return response()->json([
                'order' => $order->load('storeOrders.items'),
                'message' => 'Pagamento confirmado! Podes sair da loja.',
            ], 201);
        });
    }

    // Listagem pública de lojas com filtros por localização
    public function index(Request $request): JsonResponse
    {
        // Resultados sem geo-pesquisa são cacheáveis por 2 min
        $hasGeo    = $request->filled('lat') && $request->filled('lng');
        $hasSearch = (bool) $request->search;
        $cacheKey  = 'stores_list_' . md5(serialize($request->only(
            ['province_id','city_id','neighborhood_id','category_id','search','page']
        )));

        if (!$hasGeo && !$hasSearch) {
            $cached = \Cache::remember($cacheKey, 120, fn() => $this->buildStoreQuery($request)->paginate(20)->toArray());
            return response()->json($cached);
        }

        return response()->json($this->buildStoreQuery($request, $hasGeo)->paginate(20));
    }

    private function buildStoreQuery(Request $request, bool $hasGeo = false)
    {
        $query = Store::with(['category', 'province', 'city', 'neighborhood'])
            ->withCount(['products as total_products' => fn($q) => $q->where('is_active', true)])
            ->where('status', 'active');

        if ($request->province_id)     $query->where('province_id', $request->province_id);
        if ($request->city_id)         $query->where('city_id', $request->city_id);
        if ($request->neighborhood_id) $query->where('neighborhood_id', $request->neighborhood_id);
        if ($request->category_id)     $query->where('store_category_id', $request->category_id);
        if ($request->search)          $query->where('name', 'like', '%' . $request->search . '%');

        if ($hasGeo) {
            $lat    = (float) $request->lat;
            $lng    = (float) $request->lng;
            $radius = (float) ($request->radius ?? 15);

            $query->selectRaw("stores.*, ROUND((6371 * acos(
                        cos(radians(?)) * cos(radians(latitude)) *
                        cos(radians(longitude) - radians(?)) +
                        sin(radians(?)) * sin(radians(latitude))
                    )), 2) AS distance", [$lat, $lng, $lat])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
        } else {
            $query->orderByRaw('
                CASE WHEN visibility_expires_at > NOW() THEN visibility_position ELSE 0 END DESC,
                is_featured DESC,
                rating DESC
            ');
        }

        return $query;
    }

    public function show(string $slug): JsonResponse
    {
        $store = \Cache::remember("store_{$slug}", 180, fn() =>
            Store::with(['category', 'province', 'city', 'neighborhood', 'owner'])
                ->withCount(['products as total_products' => fn($q) => $q->where('is_active', true)])
                ->where('slug', $slug)
                ->where('status', 'active')
                ->firstOrFail()
                ->toArray()
        );

        return response()->json($store);
    }

    // Criar loja (dono da loja)
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Obrigatoriedade: apenas store_owner pode criar loja
        if ($user->role !== 'store_owner') {
            return response()->json([
                'message' => 'Apenas proprietários de loja podem criar uma loja. Atualize seu perfil para se tornar um proprietário.',
            ], 403);
        }

        $this->authorize('create', Store::class);

        $validated = $request->validate([
            'store_category_id' => 'required|exists:store_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'province_id' => 'required|exists:provinces,id',
            'city_id' => 'required|exists:cities,id',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'accepts_delivery' => 'boolean',
            'estimated_delivery_minutes' => 'nullable|integer|min:10',
            'logo' => 'nullable|image|max:2048',
            'banner' => 'nullable|image|max:5120',
        ]);

        // Obrigatoriedade: sempre associar ao user_id do dono autenticado
        $validated['user_id'] = $user->id;
        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(6);
        $validated['status'] = 'pending';

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('stores/logos', 'public');
        }
        if ($request->hasFile('banner')) {
            $validated['banner'] = $request->file('banner')->store('stores/banners', 'public');
        }

        $store = Store::create($validated);

        // Invalidar cache do utilizador para que /auth/me devolva a nova lista de lojas
        Cache::forget("user_me_{$user->id}");

        return response()->json($store->load(['category', 'province', 'city']), 201);
    }

    // Atualizar loja
    public function update(Request $request, Store $store): JsonResponse
    {
        $this->authorize('update', $store);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'province_id' => 'sometimes|exists:provinces,id',
            'city_id' => 'sometimes|exists:cities,id',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'address' => 'nullable|string',
            'accepts_delivery' => 'boolean',
            'estimated_delivery_minutes' => 'nullable|integer|min:10',
            'logo' => 'nullable|image|max:2048',
            'banner' => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('stores/logos', 'public');
        }
        if ($request->hasFile('banner')) {
            $validated['banner'] = $request->file('banner')->store('stores/banners', 'public');
        }

        $store->update($validated);

        return response()->json($store->fresh()->load(['category', 'province', 'city']));
    }

    // Actualizar a minha loja (dono autenticado) ou admin actualizando uma loja
    public function updateMyStore(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            // Admin pode actualizar qualquer loja, precisa de store_id
            $validated = $request->validate([
                'store_id' => 'required|exists:stores,id',
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'logo' => 'nullable|image|max:2048',
                'banner' => 'nullable|image|max:5120',
            ]);
            $store = Store::findOrFail($validated['store_id']);
            unset($validated['store_id']);
        } else {
            // Dono da loja actualiza a loja activa (suporta multi-loja via X-Store-Id)
            $store = $this->resolveOwnerStore($request);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'logo' => 'nullable|image|max:2048',
                'banner' => 'nullable|image|max:5120',
            ]);
        }

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('stores/logos', 'public');
        }
        if ($request->hasFile('banner')) {
            $validated['banner'] = $request->file('banner')->store('stores/banners', 'public');
        }

        $store->update($validated);
        Cache::forget('stores_page_1');

        return response()->json($store->fresh()->load(['category', 'province', 'city']));
    }

    // Dashboard do dono da loja
    public function dashboard(Request $request): JsonResponse
    {
        $store = $this->resolveOwnerStore($request);

        // Cache dashboard stats for 60s — 7 queries → 1 DB round-trip (aggregation)
        $stats = Cache::remember("store_dashboard_{$store->id}", 60, function () use ($store) {
            $productStats = $store->products()
                ->selectRaw('
                    COUNT(*) as total_products,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products
                ')
                ->first();

            $lowStock = $store->products()
                ->whereHas('stock', fn($q) => $q->whereColumn('quantity', '<=', 'minimum_stock'))
                ->count();

            $orderStats = $store->storeOrders()
                ->selectRaw('
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = "delivered" THEN subtotal ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN status = "delivered" AND MONTH(created_at) = ? AND YEAR(created_at) = ? THEN subtotal ELSE 0 END) as monthly_revenue
                ', [now()->month, now()->year])
                ->first();

            return [
                'total_products'     => (int) ($productStats->total_products ?? 0),
                'active_products'    => (int) ($productStats->active_products ?? 0),
                'low_stock_products' => $lowStock,
                'total_orders'       => (int) ($orderStats->total_orders ?? 0),
                'pending_orders'     => (int) ($orderStats->pending_orders ?? 0),
                'total_revenue'      => (float) ($orderStats->total_revenue ?? 0),
                'monthly_revenue'    => (float) ($orderStats->monthly_revenue ?? 0),
                'rating'             => $store->rating,
                'total_reviews'      => $store->total_reviews,
            ];
        });

        return response()->json([
            'store' => $store,
            'stats' => $stats,
        ]);
    }

    public function categories(): JsonResponse
    {
        $data = Cache::remember('store_categories', 3600, fn () =>
            StoreCategory::where('is_active', true)->get()
        );
        return response()->json($data);
    }

    // Minha loja (do dono autenticado)
    public function myStore(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            if ($user->role === 'admin' && $request->has('store_id')) {
                $store = Store::with(['category', 'province', 'city', 'neighborhood'])
                    ->findOrFail($request->input('store_id'));
            } else {
                $store = Store::with(['category', 'province', 'city', 'neighborhood'])
                    ->where('id', $this->resolveOwnerStore($request)->id)
                    ->firstOrFail();
            }

            return response()->json($store);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Nenhuma loja encontrada. Verifique se a loja está associada à sua conta.',
            ], 404);
        }
    }

    // Todas as lojas do dono autenticado
    public function myStores(Request $request): JsonResponse
    {
        $stores = Store::with(['category', 'province', 'city'])
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json($stores);
    }
}
