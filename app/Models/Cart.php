<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = ['user_id', 'session_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // Agrupa itens por loja
    public function itemsByStore(): \Illuminate\Support\Collection
    {
        return $this->items->groupBy('store_id');
    }

    public function getTotal(): float
    {
        return $this->items->sum(fn ($item) => $item->unit_price * $item->quantity);
    }

    public function getStoreIds(): array
    {
        return $this->items->pluck('store_id')->unique()->values()->toArray();
    }
}
