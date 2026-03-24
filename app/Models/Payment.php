<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'user_id', 'method', 'amount', 'currency',
        'status', 'transaction_id', 'reference', 'phone_number',
        'gateway_response', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'completed';
    }
}
