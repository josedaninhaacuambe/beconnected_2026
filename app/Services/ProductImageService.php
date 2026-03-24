<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductImageService
{
    /**
     * Busca uma imagem relevante para o produto na internet.
     * Usa Pexels API se a chave estiver configurada, caso contrário usa loremflickr.
     * Retorna uma URL pública (não faz download local).
     */
    public function fetchForProduct(string $productName, ?string $brandName = null): ?string
    {
        $query = trim($productName . ($brandName ? ' ' . $brandName : ''));

        // Tentar Pexels API (https://www.pexels.com/api/)
        $pexelsKey = config('services.pexels.key');
        if ($pexelsKey) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders(['Authorization' => $pexelsKey])
                    ->get('https://api.pexels.com/v1/search', [
                        'query' => $query,
                        'per_page' => 1,
                        'orientation' => 'square',
                        'size' => 'medium',
                    ]);

                if ($response->successful()) {
                    $photo = $response->json('photos.0');
                    if ($photo) {
                        return $photo['src']['medium'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Pexels API failed for "' . $query . '": ' . $e->getMessage());
            }
        }

        // Fallback gratuito: loremflickr (imagens de Flickr por palavras-chave)
        $keyword = urlencode(str_replace(' ', ',', strtolower($query)));
        return "https://loremflickr.com/400/400/{$keyword}";
    }
}
