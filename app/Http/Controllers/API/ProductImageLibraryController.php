<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductImageLibrary;
use App\Services\ImageLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductImageLibraryController extends Controller
{
    public function __construct(private ImageLibraryService $library) {}

    /**
     * GET /api/product-images?name=calgo+beny
     * Pesquisa imagens na biblioteca pelo nome do produto.
     */
    public function search(Request $request): JsonResponse
    {
        $name = trim($request->input('name', ''));
        if (strlen($name) < 2) {
            return response()->json([]);
        }

        $results = ProductImageLibrary::searchByName($name, 12);

        return response()->json($results->map(fn($img) => [
            'id'            => $img->id,
            'name'          => $img->original_name,
            'url'           => $img->url,
            'width'         => $img->width,
            'height'        => $img->height,
            'size_kb'       => round($img->size_bytes / 1024),
            'use_count'     => $img->use_count,
        ]));
    }

    /**
     * POST /api/product-images
     * Carrega uma nova imagem para a biblioteca.
     * Body: multipart — file (image), name (string)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:10240', // 10 MB
            'name' => 'required|string|max:255',
        ]);

        $record = $this->library->store(
            $request->file('file'),
            $request->input('name'),
            $request->user()->id,
        );

        if (!$record) {
            return response()->json(['message' => 'Erro ao processar imagem.'], 500);
        }

        return response()->json([
            'id'       => $record->id,
            'name'     => $record->original_name,
            'url'      => $record->url,
            'width'    => $record->width,
            'height'   => $record->height,
            'size_kb'  => round($record->size_bytes / 1024),
            'use_count'=> 0,
        ], 201);
    }

    /**
     * POST /api/product-images/{id}/use
     * Incrementa o contador de utilizações (chamado quando se usa uma imagem da biblioteca num produto).
     */
    public function markUsed(ProductImageLibrary $image): JsonResponse
    {
        $image->increment('use_count');
        return response()->json(['use_count' => $image->use_count]);
    }
}
