<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCheckout;
use App\Models\Cart;
use App\Models\CheckoutRequest;
use App\Models\Order;
use App\Models\StoreOrder;
use App\Services\DeliveryService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private DeliveryService $deliveryService,
    ) {}

    // Criar pedido a partir do carrinho
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:emola,mpesa,cash_on_delivery',
            'payment_phone' => 'required_unless:payment_method,cash_on_delivery|string|max:20',
            'delivery_address' => 'required|string',
            'province_id' => 'required|exists:provinces,id',
            'city_id' => 'required|exists:cities,id',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'delivery_latitude' => 'nullable|numeric',
            'delivery_longitude' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $cart = Cart::with('items')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'O carrinho está vazio.'], 422);
        }

        $idempotencyKey = $request->header('Idempotency-Key', (string) Str::uuid());

        $checkoutRequest = CheckoutRequest::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            ['user_id' => $user->id, 'status' => 'pending']
        );

        if ($checkoutRequest->status === 'processing') {
            return response()->json(['message' => 'Checkout já está em processamento.'], 202);
        }

        if ($checkoutRequest->status === 'succeeded') {
            return response()->json([
                'message' => 'Checkout já processado.',
                'order_id' => $checkoutRequest->order_id,
            ], 200);
        }

        if ($checkoutRequest->status === 'failed') {
            // Permitir re-tentativa caso falhas transitórias
            $checkoutRequest->update(['status' => 'pending', 'error_message' => null]);
        }

        $checkoutRequest->update(['status' => 'processing']);

        // Proteção por lock redis para evitar POST concorrente do mesmo user
        $lock = Cache::lock("checkout_user_{$user->id}", 30);
        if (!$lock->get()) {
            return response()->json(['message' => 'Já existe um checkout em andamento. Tenta novamente mais tarde.'], 429);
        }

        try {
            ProcessCheckout::dispatch($user->id, $checkoutRequest->id, $validated)->onQueue('checkout');

            return response()->json([
                'message' => 'Checkout em processamento. O pedido será criado em segundo plano.',
                'idempotency_key' => $idempotencyKey,
            ], 202);
        } finally {
            $lock->release();
        }
    }

    // Polling de status do checkout assíncrono
    public function checkoutStatus(Request $request): JsonResponse
    {
        $request->validate(['idempotency_key' => 'required|string']);

        $checkoutRequest = \App\Models\CheckoutRequest::where('idempotency_key', $request->idempotency_key)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$checkoutRequest) {
            return response()->json(['status' => 'pending'], 404);
        }

        if ($checkoutRequest->status === 'succeeded' && $checkoutRequest->order_id) {
            $order = \App\Models\Order::find($checkoutRequest->order_id);
            return response()->json([
                'status' => 'succeeded',
                'order'  => [
                    'order_number'     => $order?->order_number,
                    'delivery_address' => $order?->delivery_address,
                    'payment_method'   => $order?->payment_method,
                    'total'            => $order?->total,
                ],
            ]);
        }

        if ($checkoutRequest->status === 'failed') {
            return response()->json(['status' => 'failed', 'error' => $checkoutRequest->error_message]);
        }

        return response()->json(['status' => $checkoutRequest->status]);
    }

    // Listar pedidos do cliente
    public function myOrders(Request $request): JsonResponse
    {
        $orders = Order::with(['storeOrders.store', 'storeOrders.items', 'delivery', 'payment'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    // Detalhe de pedido
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json(
            $order->load(['storeOrders.store', 'storeOrders.items.product', 'delivery', 'payment', 'province', 'city'])
        );
    }

    // Cancelar pedido
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Este pedido não pode ser cancelado.'], 422);
        }

        DB::transaction(function () use ($order) {
            // Devolver stock
            foreach ($order->storeOrders as $storeOrder) {
                foreach ($storeOrder->items as $item) {
                    $item->product->stock?->increment('quantity', $item->quantity);
                }
            }

            $order->update(['status' => 'cancelled']);
            $order->storeOrders()->update(['status' => 'cancelled']);
            $order->delivery?->update(['status' => 'failed']);
            $order->payment?->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'Pedido cancelado.']);
    }

    // --- Pedidos da loja (dono da loja) ---

    public function storeOrders(Request $request): JsonResponse
    {
        $store = \App\Models\Store::where('user_id', $request->user()->id)->firstOrFail();

        $orders = StoreOrder::with(['order.user', 'items.product'])
            ->where('store_id', $store->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function updateStoreOrderStatus(Request $request, StoreOrder $storeOrder): JsonResponse
    {
        $store = \App\Models\Store::where('user_id', $request->user()->id)->firstOrFail();

        if ($storeOrder->store_id !== $store->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:confirmed,processing,ready,shipped',
            'store_notes' => 'nullable|string',
        ]);

        $storeOrder->update($validated);

        return response()->json(['message' => 'Estado actualizado.', 'order' => $storeOrder]);
    }
}
