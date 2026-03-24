<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CartController extends Controller
{
    private function getCart(Request $request): Cart
    {
        if ($request->user()) {
            return Cart::firstOrCreate(['user_id' => $request->user()->id]);
        }

        $sessionId = $request->cookie('cart_session') ?? session()->getId();
        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }

    public function index(Request $request): JsonResponse
    {
        $cart = $this->getCart($request);
        $cart->load(['items.product.store', 'items.product.stock', 'items.store']);

        $itemsByStore = $cart->items->groupBy('store_id')->map(function ($items, $storeId) {
            $store = $items->first()->store;
            return [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'logo' => $store->logo,
                    'estimated_delivery_minutes' => $store->estimated_delivery_minutes,
                ],
                'items' => $items->map(fn($item) => [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'images' => $item->product->images,
                        'in_stock' => ($item->product->stock?->quantity ?? 0) > 0,
                        'available_stock' => $item->product->stock?->quantity ?? 0,
                    ],
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->getSubtotal(),
                ]),
                'store_subtotal' => $items->sum(fn($i) => $i->getSubtotal()),
            ];
        })->values();

        return response()->json([
            'items_by_store' => $itemsByStore,
            'total_items' => $cart->items->count(),
            'subtotal' => $cart->getTotal(),
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $productId = $validated['product_id'];

        // Cache product availability for 30s — avoids 3 DB queries per add-to-cart
        $info = Cache::remember("cart_product_{$productId}", 30, function () use ($productId) {
            $p = Product::with(['store:id,status', 'stock:product_id,quantity'])
                ->select('id', 'store_id', 'price', 'is_active')
                ->findOrFail($productId);
            return [
                'is_active'    => $p->is_active,
                'store_status' => $p->store->status,
                'price'        => $p->price,
                'store_id'     => $p->store_id,
                'stock'        => $p->stock?->quantity ?? 0,
            ];
        });

        if (!$info['is_active'] || $info['store_status'] !== 'active') {
            return response()->json(['message' => 'Produto indisponível.'], 422);
        }

        $availableStock = $info['stock'];
        if ($availableStock < $validated['quantity']) {
            return response()->json([
                'message' => 'Stock insuficiente.',
                'available' => $availableStock,
            ], 422);
        }

        $cart = $this->getCart($request);

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->first();

        if ($cartItem) {
            $newQty = $cartItem->quantity + $validated['quantity'];
            if ($newQty > $availableStock) {
                return response()->json(['message' => 'Stock insuficiente.', 'available' => $availableStock], 422);
            }
            $cartItem->update(['quantity' => $newQty]);
        } else {
            CartItem::create([
                'cart_id'    => $cart->id,
                'product_id' => $productId,
                'store_id'   => $info['store_id'],
                'quantity'   => $validated['quantity'],
                'unit_price' => $info['price'],
            ]);
        }

        return response()->json(['message' => 'Produto adicionado ao carrinho.']);
    }

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

        return response()->json(['message' => 'Carrinho actualizado.']);
    }

    public function removeItem(CartItem $cartItem): JsonResponse
    {
        $cartItem->delete();
        return response()->json(['message' => 'Item removido do carrinho.']);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->getCart($request);
        $cart->items()->delete();
        return response()->json(['message' => 'Carrinho limpo.']);
    }
}
