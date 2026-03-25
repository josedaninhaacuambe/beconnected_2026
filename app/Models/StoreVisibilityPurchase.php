<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreVisibilityPurchase extends Model
{
    protected $fillable = [
        'store_id', 'visibility_plan_id', 'amount_paid',
        'payment_method', 'payment_reference', 'starts_at', 'expires_at',
        'status', 'next_payment_at', 'payment_notified_at', 'invoice_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'float',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'next_payment_at' => 'datetime',
            'payment_notified_at' => 'datetime',
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
