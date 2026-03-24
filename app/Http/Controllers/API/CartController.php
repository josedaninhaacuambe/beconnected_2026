<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    // ─── Cache TTLs ───────────────────────────────────────────────────────────
    private const PRODUCT_TTL   = 30;    // segundos — info de produto (preço, stock, loja)
    private const CART_ID_TTL   = 3600;  // 1 hora — cart_id por utilizador
    private const CART_ITEMS_TTL = 60;   // 60s — itens actuais do carrinho

    // ─── Obter cart_id (Redis primeiro, DB como fallback) ─────────────────────
    private function getCartId(Request $request): int
    {
        if ($user = $request->user()) {
            return (int) Cache::remember("cart_id_user_{$user->id}", self::CART_ID_TTL, function () use ($user) {
                return Cart::firstOrCreate(['user_id' => $user->id])->id;
            });
        }

        $sessionId = $request->cookie('cart_session') ?? session()->getId();
        return (int) Cache::remember("cart_id_session_{$sessionId}", self::CART_ID_TTL, function () use ($sessionId) {
            return Cart::firstOrCreate(['session_id' => $sessionId])->id;
        });
    }

    // ─── Itens do carrinho (Redis primeiro) ───────────────────────────────────
    private function getCachedItems(int $cartId): array
    {
        return Cache::remember("cart_items_{$cartId}", self::CART_ITEMS_TTL, function () use ($cartId) {
            return CartItem::where('cart_id', $cartId)
                ->get(['product_id', 'quantity'])
                ->keyBy('product_id')
                ->map(fn($i) => $i->quantity)
                ->toArray();
        });
    }

    private function invalidateCartCache(int $cartId): void
    {
        Cache::forget("cart_items_{$cartId}");
    }

    // ─── ADD ITEM — caminho crítico, máxima velocidade ────────────────────────
    // Fluxo em cache hit:
    //   • 0 leituras DB  (tudo do Redis)
    //   • 1 escrita DB   (INSERT ON DUPLICATE KEY UPDATE — atómico)
    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|min:1',   // sem exists:products,id — validado pelo cache
            'quantity'   => 'required|integer|min:1|max:100',
        ]);

        $productId = (int) $request->product_id;
        $qty       = (int) $request->quantity;

        // 1. Info do produto — Redis (30s TTL). Cache miss → 1 query c/ joins.
        $info = Cache::remember("cart_product_{$productId}", self::PRODUCT_TTL, function () use ($productId) {
            $p = Product::with(['store:id,status', 'stock:product_id,quantity'])
                ->select('id', 'store_id', 'price', 'is_active')
                ->find($productId);

            if (!$p) return null;

            return [
                'is_active'    => $p->is_active,
                'store_status' => $p->store->status,
                'price'        => $p->price,
                'store_id'     => $p->store_id,
                'stock'        => $p->stock?->quantity ?? 0,
            ];
        });

        if (!$info) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        if (!$info['is_active'] || $info['store_status'] !== 'active') {
            return response()->json(['message' => 'Produto indisponível.'], 422);
        }

        $stock = $info['stock'];

        if ($qty > $stock) {
            return response()->json(['message' => 'Stock insuficiente.', 'available' => $stock], 422);
        }

        // 2. Cart ID — Redis (1h TTL). Cache miss → firstOrCreate.
        $cartId = $this->getCartId($request);

        // 3. Itens actuais — Redis (60s TTL) para verificar quantidade já no carrinho.
        $currentItems = $this->getCachedItems($cartId);
        $currentQty   = $currentItems[$productId] ?? 0;
        $newQty       = $currentQty + $qty;

        if ($newQty > $stock) {
            return response()->json([
                'message'   => 'Stock insuficiente.',
                'available' => max(0, $stock - $currentQty),
            ], 422);
        }

        $now = now()->toDateTimeString();

        // 4. Escrita atómica — INSERT ... ON DUPLICATE KEY UPDATE
        //    Uma única query substitui: SELECT + (UPDATE ou INSERT)
        DB::statement("
            INSERT INTO cart_items (cart_id, product_id, store_id, quantity, unit_price, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                quantity   = quantity + VALUES(quantity),
                updated_at = VALUES(updated_at)
        ", [$cartId, $productId, $info['store_id'], $qty, $info['price'], $now, $now]);

        // Invalidar cache dos itens para a próxima leitura reflectir o estado real
        $this->invalidateCartCache($cartId);

        return response()->json(['message' => 'Produto adicionado ao carrinho.']);
    }

    // ─── INDEX ────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $cartId = $this->getCartId($request);

        $cart = Cart::with(['items.product.store', 'items.product.stock', 'items.store'])
            ->findOrFail($cartId);

        $itemsByStore = $cart->items->groupBy('store_id')->map(function ($items) {
            $store = $items->first()->store;
            return [
                'store' => [
                    'id'                          => $store->id,
                    'name'                        => $store->name,
                    'slug'                        => $store->slug,
                    'logo'                        => $store->logo,
                    'estimated_delivery_minutes'  => $store->estimated_delivery_minutes,
                ],
                'items' => $items->map(fn($item) => [
                    'id'       => $item->id,
                    'product'  => [
                        'id'              => $item->product->id,
                        'name'            => $item->product->name,
                        'images'          => $item->product->images,
                        'in_stock'        => ($item->product->stock?->quantity ?? 0) > 0,
                        'available_stock' => $item->product->stock?->quantity ?? 0,
                    ],
                    'quantity'   => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal'   => $item->getSubtotal(),
                ]),
                'store_subtotal' => $items->sum(fn($i) => $i->getSubtotal()),
            ];
        })->values();

        return response()->json([
            'items_by_store' => $itemsByStore,
            'total_items'    => $cart->items->count(),
            'subtotal'       => $cart->getTotal(),
        ]);
    }

    // ─── UPDATE ITEM ─────────────────────────────────────────────────────────
    public function updateItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $availableStock = $cartItem->product->stock?->quantity ?? 0;
        if ($validated['quantity'] > $availableStock) {
            return response()->json(['message' => 'Stock insuficiente.', 'available' => $availableStock], 422);
        }

        $cartItem->update(['quantity' => $validated['quantity']]);
        $this->invalidateCartCache($cartItem->cart_id);

        return response()->json(['message' => 'Carrinho actualizado.']);
    }

    // ─── REMOVE ITEM ─────────────────────────────────────────────────────────
    public function removeItem(CartItem $cartItem): JsonResponse
    {
        $cartId = $cartItem->cart_id;
        $cartItem->delete();
        $this->invalidateCartCache($cartId);

        return response()->json(['message' => 'Item removido do carrinho.']);
    }

    // ─── CLEAR ───────────────────────────────────────────────────────────────
    public function clear(Request $request): JsonResponse
    {
        $cartId = $this->getCartId($request);

        Cart::find($cartId)?->items()->delete();
        $this->invalidateCartCache($cartId);

        return response()->json(['message' => 'Carrinho limpo.']);
    }
}
