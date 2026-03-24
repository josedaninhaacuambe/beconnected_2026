<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreVisibilityPurchase extends Model
{
    protected $fillable = [
        'store_id', 'visibility_plan_id', 'amount_paid',
        'payment_method', 'payment_reference', 'starts_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'float',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(VisibilityPlan::class, 'visibility_plan_id');
    }
}
