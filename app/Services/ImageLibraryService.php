<?php

namespace App\Services;

use App\Models\ProductImageLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageLibraryService
{
    /** Tamanho máximo de entrada: 10 MB */
    const MAX_BYTES = 10 * 1024 * 1024;

    /** Largura máxima após redimensionamento */
    const MAX_WIDTH = 1200;

    /** Qualidade WebP (0-100) */
    const WEBP_QUALITY = 82;

    /**
     * Comprime e guarda um ficheiro na biblioteca.
     * Retorna o registo criado ou null em caso de erro.
     */
    public function store(UploadedFile $file, string $productName, ?int $userId = null): ?ProductImageLibrary
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('A imagem não pode ter mais de 10 MB.');
        }

        try {
            // Ler e redimensionar com Intervention Image v3
            $image = Image::read($file->getRealPath());

            // Redimensionar só se for mais largo que MAX_WIDTH (preserva proporção)
            if ($image->width() > self::MAX_WIDTH) {
                $image->scaleDown(width: self::MAX_WIDTH);
            }

            $w = $image->width();
            $h = $image->height();

            // Codificar para WebP
            $encoded = $image->toWebp(self::WEBP_QUALITY);

            // Nome do ficheiro: slug do produto + hash único
            $slug     = Str::slug($productName);
            $filename = "library/{$slug}-" . Str::random(10) . '.webp';

            Storage::disk('public')->put($filename, (string) $encoded);

            $sizeBytes = Storage::disk('public')->size($filename);

            return ProductImageLibrary::create([
                'name'          => ProductImageLibrary::normalizeName($productName),
                'original_name' => trim($productName),
                'path'          => $filename,
                'size_bytes'    => $sizeBytes,
                'width'         => $w,
                'height'        => $h,
                'use_count'     => 0,
                'uploaded_by'   => $userId,
            ]);
        } catch (\Throwable $e) {
            \Log::error('[ImageLibrary] Erro ao guardar imagem: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Copia uma imagem já existente no storage (path local) para a biblioteca.
     * Usado quando storeProduct() guarda uma imagem e a adiciona automaticamente.
     */
    public function addFromPath(string $storagePath, string $productName, ?int $userId = null): ?ProductImageLibrary
    {
        try {
            $fullPath = Storage::disk('public')->path($storagePath);
            if (!file_exists($fullPath)) return null;

            $image = Image::read($fullPath);

            if ($image->width() > self::MAX_WIDTH) {
                $image->scaleDown(width: self::MAX_WIDTH);
            }

            $w = $image->width();
            $h = $image->height();

            $slug     = Str::slug($productName);
            $filename = "library/{$slug}-" . Str::random(10) . '.webp';

            $encoded = $image->toWebp(self::WEBP_QUALITY);
            Storage::disk('public')->put($filename, (string) $encoded);

            return ProductImageLibrary::create([
                'name'          => ProductImageLibrary::normalizeName($productName),
                'original_name' => trim($productName),
                'path'          => $filename,
                'size_bytes'    => Storage::disk('public')->size($filename),
                'width'         => $w,
                'height'        => $h,
                'use_count'     => 1,
                'uploaded_by'   => $userId,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[ImageLibrary] addFromPath falhou: ' . $e->getMessage());
            return null;
        }
    }
}
