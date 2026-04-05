<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexProductInSearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private int  $productId,
        private bool $delete = false,
    ) {
        $this->onQueue('search_index');
    }

    public function handle(): void
    {
        $host = config('services.meilisearch.host');
        $key  = config('services.meilisearch.key');

        if (!$host) {
            return; // Meilisearch não configurado — ignorar silenciosamente
        }

        if ($this->delete) {
            Http::withToken($key)
                ->delete("{$host}/indexes/products/documents/{$this->productId}");
            return;
        }

        $product = Product::with(['brand', 'category', 'store.province', 'store.city', 'stock'])
            ->find($this->productId);

        if (!$product || !$product->is_active || $product->store?->status !== 'active') {
            // Produto inactivo — remover do índice
            Http::withToken($key)
                ->delete("{$host}/indexes/products/documents/{$this->productId}");
            return;
        }

        $document = [
            'id'              => $product->id,
            'name'            => $product->name,
            'model'           => $product->model,
            'sku'             => $product->sku,
            'barcode'         => $product->barcode,
            'description'     => mb_substr($product->description ?? '', 0, 200),
            'brand_name'      => $product->brand?->name,
            'category_name'   => $product->category?->name,
            'category_id'     => $product->product_category_id,
            'brand_id'        => $product->brand_id,
            'store_name'      => $product->store?->name,
            'store_slug'      => $product->store?->slug,
            'store_city_id'   => $product->store?->city_id,
            'store_province_id' => $product->store?->province_id,
            'has_visibility'  => $product->store?->hasActiveVisibility() ? 1 : 0,
            'is_featured'     => $product->is_featured ? 1 : 0,
            'stock_quantity'  => $product->stock?->quantity ?? 0,
            'total_sold'      => $product->total_sold ?? 0,
            'rating'          => $product->rating ?? 0,
            'images'          => $product->images ?? [],
            'created_at_ts'   => $product->created_at?->timestamp ?? 0,
        ];

        Http::withToken($key)
            ->put("{$host}/indexes/products/documents", [$document]);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('IndexProductInSearch falhou', ['product_id' => $this->productId, 'error' => $e->getMessage()]);
    }
}
