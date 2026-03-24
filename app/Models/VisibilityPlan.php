<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisibilityPlan extends Model
{
    protected $fillable = [
        'name', 'description', 'price', 'duration_days',
        'position_boost', 'is_featured_badge', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_featured_badge' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(StoreVisibilityPurchase::class);
    }
}
