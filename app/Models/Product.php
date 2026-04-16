<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id', 'product_category_id', 'store_section_id', 'brand_id', 'name', 'slug',
        'description', 'price', 'cost_price', 'compare_price', 'flash_price', 'flash_until',
        'sku', 'barcode', 'model', 'images', 'attributes', 'is_active', 'is_featured',
        'total_sold', 'is_weighable', 'weight_unit', 'waste_margin', 'availability', 'selling_modes',
    ];

    protected $casts = [
        'price' => 'float',
        'cost_price' => 'float',
        'is_weighable'  => 'boolean',
        'waste_margin'  => 'float',
        'compare_price' => 'float',
        'flash_price' => 'float',
        'flash_until' => 'datetime',
        'images' => 'array',
        'attributes' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'availability' => 'string',
        'selling_modes' => 'array',
        'rating' => 'float',
        'total_sold' => 'integer',
    ];

    protected static ?bool $hasAvailabilityColumn = null;

    public static function hasAvailabilityColumn(): bool
    {
        // Remover cache estático para garantir que a migração seja detectada
        return Schema::hasColumn((new self)->getTable(), 'availability');
    }

    public function scopeForVirtualStore($query)
    {
        if (self::hasAvailabilityColumn()) {
            return $query->whereIn('availability', ['virtual_store', 'both']);
        }
        return $query->where(fn($q) => $q->where('pos_only', false)->orWhereNull('pos_only'));
    }

    public function scopeForPos($query)
    {
        if (self::hasAvailabilityColumn()) {
            // Inclui: pos, both, e NULL (produtos criados sem definir availability)
            return $query->where(function ($q) {
                $q->whereIn('availability', ['pos', 'both'])
                  ->orWhereNull('availability');
            });
        }
        // Fallback: show all products (since old logic showed all in POS)
        return $query;
    }

    public function scopeExcludePosOnly($query)
    {
        return $query->forVirtualStore();
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
