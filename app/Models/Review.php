<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    protected $fillable = ['user_id', 'order_id', 'rating', 'comment', 'images', 'is_approved'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_approved' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}
