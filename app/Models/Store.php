<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'store_category_id', 'name', 'slug', 'description',
        'logo', 'banner', 'phone', 'whatsapp', 'email',
        'province_id', 'city_id', 'neighborhood_id', 'address',
        'latitude', 'longitude', 'status', 'is_featured',
        'visibility_position', 'visibility_expires_at',
        'accepts_delivery', 'estimated_delivery_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'accepts_delivery' => 'boolean',
            'visibility_expires_at' => 'datetime',
            'rating' => 'float',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class, 'store_category_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function visibilityPurchases(): HasMany
    {
        return $this->hasMany(StoreVisibilityPurchase::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getEffectiveVisibilityPosition(): int
    {
        if ($this->visibility_expires_at && $this->visibility_expires_at->isFuture()) {
            return $this->visibility_position;
        }
        return 0;
    }

    public function getActiveVisibilityPlan()
    {
        return $this->visibilityPurchases()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with('plan')
            ->first()?->plan;
    }

    public function hasActiveVisibility(): bool
    {
        return $this->getActiveVisibilityPlan() !== null;
    }

    public function getMaxProducts(): int
    {
        $plan = $this->getActiveVisibilityPlan();
        return match ($plan?->price) {
            500 => 500, // or unlimited? but user said for no package 100
            1000 => 1000,
            2000 => 2000,
            15000 => 15000,
            default => 100, // no package
        };
    }

    public function getMaxEmployees(): int
    {
        $plan = $this->getActiveVisibilityPlan();
        return match ($plan?->price) {
            500 => 1,
            1000 => 2,
            2000 => 5,
            15000 => 999, // unlimited
            default => 1,
        };
    }

    public function canBurnStock(): bool
    {
        $plan = $this->getActiveVisibilityPlan();
        return in_array($plan?->price, [1000, 2000, 15000]);
    }

    public function getMaxStockBurnPerDay(): int
    {
        $plan = $this->getActiveVisibilityPlan();
        return match ($plan?->price) {
            1000 => 10,
            2000 => 20,
            15000 => 50,
            default => 0,
        };
    }

    public function canImportStock(): bool
    {
        $plan = $this->getActiveVisibilityPlan();
        return in_array($plan?->price, [500, 1000, 2000, 15000]);
    }

    public function canCustomizeProfile(): bool
    {
        $plan = $this->getActiveVisibilityPlan();
        return in_array($plan?->price, [2000, 15000]);
    }

    public function canUsePOS(): bool
    {
        $plan = $this->getActiveVisibilityPlan();
        return $plan?->price === 15000;
    }

    public function canUseScanAndGo(): bool
    {
        return $this->canUsePOS(); // only 15000
    }

    public function canOrganizeSections(): bool
    {
        $plan = $this->getActiveVisibilityPlan();
        return in_array($plan?->price, [1000, 2000, 15000]);
    }
}
