<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pos_sale_id', 'product_id', 'product_name', 'product_sku',
        'unit_price', 'cost_price', 'quantity', 'subtotal',
    ];

    public function sale(): BelongsTo    { return $this->belongsTo(PosSale::class, 'pos_sale_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
