<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['store_order_id', 'product_id', 'product_name', 'unit_price', 'quantity', 'total'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'float',
            'total' => 'float',
        ];
    }

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
