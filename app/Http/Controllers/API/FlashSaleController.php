<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FlashSaleController extends Controller
{
    /**
     * Listar queimas activas do dono da loja
     */
    public function index(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $products = Product::with(['stock'])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->whereNotNull('flash_price')
            ->whereNotNull('flash_until')
            ->where('flash_until', '>', now())
            ->orderBy('flash_until')
            ->get();

        return response()->json($products);
    }

    /**
     * Lançar queima de stock para um produto
     */
    public function launch(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'product_id'  => 'required|exists:products,id',
            'flash_price' => 'required|numeric|min:1',
            'flash_until' => 'required|date|after:now',
            'message'     => 'nullable|string|max:200',
        ]);

        $product = Product::where('id', $validated['product_id'])
            ->where('store_id', $store->id)
            ->firstOrFail();

        if ($validated['flash_price'] >= $product->price) {
            return response()->json([
                'message' => 'O preço de queima deve ser inferior ao preço normal.',
            ], 422);
        }

        $product->update([
            'flash_price' => $validated['flash_price'],
            'flash_until' => $validated['flash_until'],
        ]);

        // Limpar cache para aparecer imediatamente
        Cache::forget('products_flash');

        // Notificar todos os clientes activos
        $this->notifyAllUsers($product, $store, $validated['message'] ?? null);

        return response()->json([
            'message' => 'Queima lançada com sucesso! Todos os clientes foram notificados.',
            'product' => $product->fresh()->load('stock'),
        ]);
    }

    /**
     * Cancelar queima de um produto
     */
    public function cancel(Request $request, Product $product): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        if ($product->store_id !== $store->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $product->update(['flash_price' => null, 'flash_until' => null]);
        Cache::forget('products_flash');

        return response()->json(['message' => 'Queima cancelada.']);
    }

    /**
     * Todos os produtos da loja elegíveis para queima (activos, com stock)
     */
    public function eligibleProducts(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $products = Product::with(['stock', 'brand'])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->whereHas('stock', fn($q) => $q->where('quantity', '>', 0))
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'price'       => $p->price,
                'flash_price' => $p->flash_price,
                'flash_until' => $p->flash_until?->toISOString(),
                'stock'       => $p->stock?->quantity ?? 0,
                'images'      => $p->images ?? [],
                'has_active_flash' => $p->flash_until && $p->flash_until->isFuture(),
            ]);

        return response()->json($products);
    }

    /**
     * Criar notificações para todos os utilizadores activos
     */
    private function notifyAllUsers(Product $product, Store $store, ?string $extraMessage): void
    {
        $discount = round((($product->price - $product->flash_price) / $product->price) * 100);
        $title = "⚡ Queima de Stock — {$store->name}";
        $body  = "{$product->name} com {$discount}% de desconto! "
               . "Preço normal: {$product->price} MZN → Queima: {$product->flash_price} MZN."
               . ($extraMessage ? " {$extraMessage}" : '');

        $data = [
            'product_id'   => $product->id,
            'product_slug' => $product->slug,
            'store_id'     => $store->id,
            'store_slug'   => $store->slug,
            'store_name'   => $store->name,
            'flash_price'  => $product->flash_price,
            'original_price' => $product->price,
            'flash_until'  => $product->flash_until->toISOString(),
            'discount_pct' => $discount,
        ];

        // Inserção em lote para não bloquear o request
        $userIds = User::where('is_active', true)
            ->where('role', 'customer')
            ->pluck('id');

        $now    = now();
        $chunks = $userIds->chunk(500);

        foreach ($chunks as $chunk) {
            $rows = $chunk->map(fn($uid) => [
                'user_id'    => $uid,
                'type'       => 'flash_sale',
                'title'      => $title,
                'body'       => $body,
                'data'       => json_encode($data),
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            DB::table('store_notifications')->insert($rows);
        }
    }
}
