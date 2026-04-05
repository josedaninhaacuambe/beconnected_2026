<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyOrderStatusChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    private const MESSAGES = [
        'confirmed'  => '✅ O teu pedido foi confirmado pela loja!',
        'processing' => '🔄 A tua encomenda está a ser preparada.',
        'ready'      => '📦 A tua encomenda está pronta para entrega!',
        'shipped'    => '🚴 O teu pedido está a caminho!',
        'delivered'  => '🎉 Encomenda entregue! Avalia a tua experiência.',
        'cancelled'  => '❌ O teu pedido foi cancelado.',
        'paid'       => '💳 Pagamento confirmado com sucesso!',
    ];

    public function __construct(
        private int    $orderId,
        private string $status,
        private string $context = 'order', // 'order' | 'payment' | 'delivery'
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $order = Order::with('user')->find($this->orderId);

        if (!$order) {
            return;
        }

        $message = self::MESSAGES[$this->status] ?? null;

        if (!$message) {
            return;
        }

        // Broadcast via Soketi para notificação em tempo real no frontend
        $this->broadcastToUser($order->user_id, [
            'type'     => 'order_status',
            'order_id' => $this->orderId,
            'status'   => $this->status,
            'message'  => $message,
        ]);

        Log::info('Notificação de pedido enviada', [
            'order_id' => $this->orderId,
            'status'   => $this->status,
            'user_id'  => $order->user_id,
        ]);
    }

    private function broadcastToUser(int $userId, array $data): void
    {
        try {
            $host   = config('broadcasting.connections.pusher.options.host', 'soketi');
            $port   = config('broadcasting.connections.pusher.options.port', 6001);
            $appId  = config('broadcasting.connections.pusher.app_id');
            $key    = config('broadcasting.connections.pusher.key');
            $secret = config('broadcasting.connections.pusher.secret');

            if (!$appId || !$key || !$secret) {
                return;
            }

            $channel   = "private-user.{$userId}";
            $event     = 'OrderStatusUpdated';
            $body      = json_encode($data);
            $timestamp = time();
            $authStr   = "POST\n/apps/{$appId}/events\n" .
                http_build_query([
                    'auth_key'       => $key,
                    'auth_timestamp' => $timestamp,
                    'auth_version'   => '1.0',
                    'body_md5'       => md5($body),
                ]);

            $signature = hash_hmac('sha256', $authStr, $secret);

            \Illuminate\Support\Facades\Http::post(
                "http://{$host}:{$port}/apps/{$appId}/events",
                [
                    'name'     => $event,
                    'channel'  => $channel,
                    'data'     => $body,
                ]
            )->throw();
        } catch (\Throwable) {
            // Soketi pode não estar activo em dev — ignorar
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('NotifyOrderStatusChange falhou', ['order_id' => $this->orderId, 'error' => $e->getMessage()]);
    }
}
