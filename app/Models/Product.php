<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id', 'product_category_id', 'store_section_id', 'brand_id', 'name', 'slug',
        'description', 'price', 'compare_price', 'flash_price', 'flash_until',
        'sku', 'barcode', 'model', 'images', 'attributes', 'is_active', 'is_featured',
        'total_sold',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'compare_price' => 'float',
            'flash_price' => 'float',
            'flash_until' => 'datetime',
            'images' => 'array',
            'attributes' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'rating' => 'float',
            'total_sold' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function storeSection(): BelongsTo
    {
        return $this->belongsTo(StoreSection::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(ProductStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    // Retorna produto sem preco para listagens/pesquisas globais
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'images' => $this->images,
            'brand' => $this->brand?->name,
            'model' => $this->model,
            'category' => $this->category?->name,
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'logo' => $this->store->logo,
                'city' => $this->store->city?->name,
                'rating' => $this->store->rating,
            ],
            'rating' => $this->rating,
            'total_reviews' => $this->total_reviews,
            'in_stock' => ($this->stock?->quantity ?? 0) > 0,
        ];
    }
}
