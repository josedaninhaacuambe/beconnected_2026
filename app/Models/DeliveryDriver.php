<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryDriver extends Model
{
    protected $fillable = [
        'user_id', 'vehicle_type', 'license_plate', 'id_document',
        'is_available', 'current_latitude', 'current_longitude', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'current_latitude' => 'float',
            'current_longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }
}
