<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImageLibrary extends Model
{
    protected $table = 'product_images_library';

    protected $fillable = [
        'name', 'original_name', 'path',
        'size_bytes', 'width', 'height',
        'use_count', 'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width'      => 'integer',
        'height'     => 'integer',
        'use_count'  => 'integer',
    ];

    protected $appends = ['url'];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** Normaliza um nome de produto para pesquisa consistente */
    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    /** Pesquisa por nome — divide em palavras e exige que todas existam */
    public static function searchByName(string $name, int $limit = 12): \Illuminate\Database\Eloquent\Collection
    {
        $normalized = self::normalizeName($name);
        $words = array_filter(explode(' ', $normalized), fn($w) => strlen($w) >= 2);

        if (empty($words)) return collect();

        $query = self::query();
        foreach ($words as $word) {
            $query->where('name', 'like', '%' . $word . '%');
        }

        return $query->orderByDesc('use_count')->orderByDesc('created_at')->limit($limit)->get();
    }
}
