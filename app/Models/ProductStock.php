<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStock extends Model
{
    protected $table = 'product_stock';

    protected $fillable = ['product_id', 'quantity', 'minimum_stock', 'unit'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isLow(): bool
    {
        return $this->quantity <= $this->minimum_stock;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }
}
