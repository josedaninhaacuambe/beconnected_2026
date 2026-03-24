<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $fillable = [
        'order_id', 'driver_id', 'tracking_code', 'status', 'fee', 'distance_km',
        'pickup_address', 'pickup_latitude', 'pickup_longitude',
        'dropoff_address', 'dropoff_latitude', 'dropoff_longitude',
        'assigned_at', 'picked_up_at', 'delivered_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'float',
            'distance_km' => 'float',
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($delivery) {
            $delivery->tracking_code = 'TRK-' . strtoupper(uniqid());
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(DeliveryDriver::class, 'driver_id');
    }
}
