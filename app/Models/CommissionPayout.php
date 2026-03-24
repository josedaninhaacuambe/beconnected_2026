<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionPayout extends Model
{
    protected $fillable = [
        'total_amount', 'total_commissions', 'payment_method',
        'recipient_phone', 'status', 'transaction_id',
        'payment_reference', 'gateway_response', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'float',
            'gateway_response' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
