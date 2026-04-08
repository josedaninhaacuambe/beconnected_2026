<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    protected $fillable = [
        'store_id', 'user_id', 'local_id', 'subtotal', 'discount', 'apply_vat', 'vat_rate',
        'vat_amount', 'total', 'payment_method', 'customer_name', 'customer_phone', 'notes',
        'synced', 'sale_at',
    ];

    protected function casts(): array
    {
        return [
            'synced'    => 'boolean',
            'apply_vat' => 'boolean',
            'vat_rate'  => 'float',
            'vat_amount'=> 'float',
            'sale_at'   => 'datetime',
        ];
    }

    public function store(): BelongsTo  { return $this->belongsTo(Store::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function items(): HasMany    { return $this->hasMany(PosSaleItem::class); }
}
