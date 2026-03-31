<?php

namespace App\Jobs;

use App\Models\Cart;
use App\Models\CheckoutRequest;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductStock;
use App\Models\StoreOrder;
use App\Services\DeliveryService;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCheckout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $userId;
    protected int $checkoutRequestId;
    protected array $validated;

    public function __construct(int $userId, int $checkoutRequestId, array $validated)
    {
        $this->userId = $userId;
        $this->checkoutRequestId = $checkoutRequestId;
        $this->validated = $validated;
    }

    public function handle(PaymentService $paymentService, DeliveryService $deliveryService): void
    {
        $checkoutRequest = CheckoutRequest::find($this->checkoutRequestId);
        if (!$checkoutRequest) {
            Log::error('CheckoutRequest não encontrado', ['checkout_request_id' => $this->checkoutRequestId]);
            return;
        }

        if ($checkoutRequest->status === 'succeeded') {
            Log::info('Checkout já processado com idempotency', ['checkout_request_id' => $this->checkoutRequestId]);
            return;
        }

        $lock = Cache::lock("checkout_processing_user_{$this->userId}", 120);
        if (!$lock->block(5)) {
            $checkoutRequest->update(['status' => 'failed', 'error_message' => 'Lock de checkout esgotado']);
            Log::warning('Não foi possível obter lock de checkout', ['user_id' => $this->userId]);
            return;
        }

        try {
            $checkoutRequest->update(['status' => 'processing']);

            $cart = Cart::with(['items.product.store', 'items.product.stock'])
                ->where('user_id', $this->userId)
                ->first();

            if (!$cart || $cart->items->isEmpty()) {
                $checkoutRequest->update(['status' => 'failed', 'error_message' => 'Carrinho vazio']);
                Log::warning('Checkout failed: carrinho vazio', ['user_id' => $this->userId]);
                return;
            }

            DB::transaction(function () use ($cart, $paymentService, $deliveryService, $checkoutRequest) {
                foreach ($cart->items as $item) {
                    $productStock = ProductStock::where('product_id', $item->product_id)->lockForUpdate()->first();
                    $availableStock = $productStock?->quantity ?? 0;

                    if ($availableStock < $item->quantity) {
                        throw new \RuntimeException("Stock insuficiente para produto {$item->product_id}");
                    }
                }

                $subtotal = $cart->getTotal();
                $deliveryFee = $deliveryService->calculateFee($this->validated['city_id']);

                $order = Order::create([
                    'user_id' => $this->userId,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total' => $subtotal + $deliveryFee,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'payment_method' => $this->validated['payment_method'],
                    'delivery_address' => $this->validated['delivery_address'],
                    'province_id' => $this->validated['province_id'],
                    'city_id' => $this->validated['city_id'],
                    'neighborhood_id' => $this->validated['neighborhood_id'] ?? null,
                    'delivery_latitude' => $this->validated['delivery_latitude'] ?? null,
                    'delivery_longitude' => $this->validated['delivery_longitude'] ?? null,
                    'notes' => $this->validated['notes'] ?? null,
                ]);

                foreach ($cart->itemsByStore() as $storeId => $itemsByStore) {
                    $storeSubtotal = $itemsByStore->sum(fn($i) => $i->getSubtotal());

                    $storeOrder = StoreOrder::create([
                        'order_id' => $order->id,
                        'store_id' => $storeId,
                        'subtotal' => $storeSubtotal,
                        'status' => 'pending',
                    ]);

                    foreach ($itemsByStore as $item) {
                        OrderItem::create([
                            'store_order_id' => $storeOrder->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name,
                            'unit_price' => $item->unit_price,
                            'quantity' => $item->quantity,
                            'total' => $item->getSubtotal(),
                        ]);

                        $product = $item->product;
                        $stock = ProductStock::where('product_id', $product->id)->lockForUpdate()->first();

                        if (!$stock) {
                            throw new \RuntimeException("Stock record não encontrado para produto {$product->id}");
                        }

                        $stock->decrement('quantity', $item->quantity);
                        $product->increment('total_sold', $item->quantity);

                        $product->stockMovements()->create([
                            'type' => 'out',
                            'quantity' => $item->quantity,
                            'quantity_before' => $stock->quantity + $item->quantity,
                            'quantity_after' => $stock->quantity,
                            'reason' => 'Venda - Pedido #' . $order->order_number,
                            'user_id' => $this->userId,
                        ]);

                        Cache::forget("cart_product_{$product->id}");
                        Cache::forget('products_flash');
                        Cache::forget('products_trending');

                        foreach (['featured', 'newest', 'price_asc', 'price_desc'] as $sort) {
                            for ($pg = 1; $pg <= 3; $pg++) {
                                Cache::forget("store_products_{$product->store->slug}_p{$pg}_{$sort}");
                            }
                        }
                    }
                }

                Delivery::create([
                    'order_id' => $order->id,
                    'status' => 'pending',
                    'fee' => $deliveryFee,
                    'dropoff_address' => $this->validated['delivery_address'],
                    'dropoff_latitude' => $this->validated['delivery_latitude'] ?? null,
                    'dropoff_longitude' => $this->validated['delivery_longitude'] ?? null,
                ]);

                $payment = Payment::create([
                    'order_id' => $order->id,
                    'user_id' => $this->userId,
                    'method' => $this->validated['payment_method'],
                    'amount' => $order->total,
                    'currency' => 'MZN',
                    'status' => 'pending',
                    'phone_number' => $this->validated['payment_phone'] ?? null,
                ]);

                $cart->items()->delete();

                $paymentService->initiate($payment, $this->validated['payment_phone'] ?? null);

                $checkoutRequest->update(['status' => 'succeeded', 'order_id' => $order->id]);

                Log::info('Checkout processado em background', ['order_id' => $order->id, 'user_id' => $this->userId]);
            });
        } catch (\Throwable $e) {
            $checkoutRequest->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error('Erro no processamento do checkout', ['error' => $e->getMessage(), 'user_id' => $this->userId]);
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
