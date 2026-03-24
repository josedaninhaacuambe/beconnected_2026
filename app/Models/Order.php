<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number', 'user_id', 'subtotal', 'delivery_fee', 'total',
        'status', 'payment_status', 'payment_method',
        'delivery_address', 'province_id', 'city_id', 'neighborhood_id',
        'delivery_latitude', 'delivery_longitude', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'float',
            'delivery_fee' => 'float',
            'total' => 'float',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($order) {
            $order->order_number = 'BC-' . strtoupper(uniqid());
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }
}
