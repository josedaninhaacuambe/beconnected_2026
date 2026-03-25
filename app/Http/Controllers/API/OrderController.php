<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StoreOrder;
use App\Services\DeliveryService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $cart = Cart::with(['items.product.store', 'items.product.stock'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'O carrinho está vazio.'], 422);
        }

        // Verificar stock
        foreach ($cart->items as $item) {
            $stock = $item->product->stock?->quantity ?? 0;
            if ($stock < $item->quantity) {
                return response()->json([
                    'message' => "Stock insuficiente para: {$item->product->name}",
                    'available' => $stock,
                ], 422);
            }
        }

        // Calcular taxa de entrega fora da transação (pode fazer query)
        $subtotal    = $cart->getTotal();
        $deliveryFee = $this->deliveryService->calculateFee($validated['city_id']);

        // A transação deve ser o mais curta possível — só escrita na DB
        // O gateway de pagamento é chamado DEPOIS para não bloquear conexões DB
        [$order, $payment] = DB::transaction(function () use ($validated, $user, $cart, $subtotal, $deliveryFee) {
            $order = Order::create([
                'user_id'            => $user->id,
                'subtotal'           => $subtotal,
                'delivery_fee'       => $deliveryFee,
                'total'              => $subtotal + $deliveryFee,
                'status'             => 'pending',
                'payment_status'     => 'pending',
                'payment_method'     => $validated['payment_method'],
                'delivery_address'   => $validated['delivery_address'],
                'province_id'        => $validated['province_id'],
                'city_id'            => $validated['city_id'],
                'neighborhood_id'    => $validated['neighborhood_id'] ?? null,
                'delivery_latitude'  => $validated['delivery_latitude'] ?? null,
                'delivery_longitude' => $validated['delivery_longitude'] ?? null,
                'notes'              => $validated['notes'] ?? null,
            ]);

            foreach ($cart->itemsByStore() as $storeId => $items) {
                $storeSubtotal = $items->sum(fn($i) => $i->getSubtotal());

                $storeOrder = StoreOrder::create([
                    'order_id' => $order->id,
                    'store_id' => $storeId,
                    'subtotal' => $storeSubtotal,
                    'status'   => 'pending',
                ]);

                foreach ($items as $item) {
                    OrderItem::create([
                        'store_order_id' => $storeOrder->id,
                        'product_id'     => $item->product_id,
                        'product_name'   => $item->product->name,
                        'unit_price'     => $item->unit_price,
                        'quantity'       => $item->quantity,
                        'total'          => $item->getSubtotal(),
                    ]);

                    $item->product->stock->decrement('quantity', $item->quantity);
                    $item->product->increment('total_sold', $item->quantity);
                    $item->product->stockMovements()->create([
                        'type'            => 'out',
                        'quantity'        => $item->quantity,
                        'quantity_before' => $item->product->stock->quantity + $item->quantity,
                        'quantity_after'  => $item->product->stock->quantity,
                        'reason'          => 'Venda - Pedido #' . $order->order_number,
                        'user_id'         => $user->id,
                    ]);

                    // Invalidar caches de produto e da loja para reflectir stock actualizado
                    \Illuminate\Support\Facades\Cache::forget("cart_product_{$item->product_id}");
                    \Illuminate\Support\Facades\Cache::forget('products_flash');
                    \Illuminate\Support\Facades\Cache::forget('products_trending');
                    // Invalidar todas as páginas da loja (páginas 1-3, ordenações comuns)
                    foreach (['featured', 'newest', 'price_asc', 'price_desc'] as $sort) {
                        for ($pg = 1; $pg <= 3; $pg++) {
                            \Illuminate\Support\Facades\Cache::forget("store_products_{$item->product->store->slug}_p{$pg}_{$sort}");
                        }
                    }
                }
            }

            Delivery::create([
                'order_id'          => $order->id,
                'status'            => 'pending',
                'fee'               => $deliveryFee,
                'dropoff_address'   => $validated['delivery_address'],
                'dropoff_latitude'  => $validated['delivery_latitude'] ?? null,
                'dropoff_longitude' => $validated['delivery_longitude'] ?? null,
            ]);

            $payment = Payment::create([
                'order_id'     => $order->id,
                'user_id'      => $user->id,
                'method'       => $validated['payment_method'],
                'amount'       => $order->total,
                'currency'     => 'MZN',
                'status'       => 'pending',
                'phone_number' => $validated['payment_phone'] ?? null,
            ]);

            $cart->items()->delete();

            return [$order, $payment];
        });

        // Iniciar pagamento FORA da transação — chamada HTTP externa não bloqueia DB
        $paymentResult = $this->paymentService->initiate($payment, $validated['payment_phone'] ?? null);

        return response()->json([
            'order'   => $order->load(['storeOrders.items', 'delivery', 'payment']),
            'payment' => $paymentResult,
            'message' => 'Pedido criado com sucesso.',
        ], 201);
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
