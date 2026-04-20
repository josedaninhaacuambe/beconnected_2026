<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id', 'type', 'quantity', 'quantity_before',
        'quantity_after', 'reason', 'user_id',
        'entry_mode', 'units_per_box', 'boxes_count', 'acquisition_price', 'expiry_date',
    ];

    protected $casts = [
        'units_per_box'     => 'float',
        'boxes_count'       => 'float',
        'acquisition_price' => 'float',
        'expiry_date'       => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
