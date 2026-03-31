<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class BroadcastDriverLocation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'delivery';
    public int $tries = 2;
    public int $backoff = 5;

    public function __construct(
        private int    $driverId,
        private float  $latitude,
        private float  $longitude,
        private string $trackingCode,
    ) {}

    public function handle(): void
    {
        // 1. Guardar posição no Redis (geospatial) para consultas rápidas de proximidade
        Redis::geoadd(
            'drivers_locations',
            $this->longitude,
            $this->latitude,
            "driver:{$this->driverId}"
        );

        // 2. Guardar timestamp da última actualização
        Redis::setex("driver_last_seen:{$this->driverId}", 300, now()->toISOString());

        // 3. Broadcast via Soketi para o cliente a rastrear a entrega
        $this->broadcastToChannel("delivery.{$this->trackingCode}", [
            'driver_id'    => $this->driverId,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'tracking_code' => $this->trackingCode,
            'updated_at'   => now()->toISOString(),
        ]);
    }

    private function broadcastToChannel(string $channel, array $data): void
    {
        try {
            $host   = config('broadcasting.connections.pusher.options.host', 'soketi');
            $port   = config('broadcasting.connections.pusher.options.port', 6001);
            $appId  = config('broadcasting.connections.pusher.app_id');
            $key    = config('broadcasting.connections.pusher.key');
            $secret = config('broadcasting.connections.pusher.secret');

            if (!$appId) {
                return;
            }

            Http::post("http://{$host}:{$port}/apps/{$appId}/events", [
                'name'    => 'DriverLocationUpdated',
                'channel' => $channel,
                'data'    => json_encode($data),
            ]);
        } catch (\Throwable) {
            // Soketi indisponível — ignorar silenciosamente
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('BroadcastDriverLocation falhou', [
            'driver_id' => $this->driverId,
            'error'     => $e->getMessage(),
        ]);
    }
}
