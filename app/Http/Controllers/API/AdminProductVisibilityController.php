<?php

namespace App\Http\Controllers\API;

use App\Models\AdminAuditLog;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AdminProductVisibilityController extends Controller
{
    /**
     * Listar produtos que requerem aprovação
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $query = Product::where('is_visible_to_public', false)
            ->whereNull('approved_at')
            ->with('store:id,name', 'category:id,name')
            ->select([
                'id', 'name', 'store_id', 'category_id',
                'price', 'is_visible_to_public', 'approved_at', 'created_at'
            ]);

        // Filtro por loja
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        // Busca por nome
        if ($request->has('search')) {
            $searchTerm = "%{$request->search}%";
            $query->where('name', 'like', $searchTerm);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($products);
    }

    /**
     * Listar todos os produtos de uma loja
     */
    public function storeProducts(Store $store, Request $request): JsonResponse
    {
        $query = $store->products()
            ->select([
                'id', 'name', 'price', 'is_visible_to_public',
                'is_active', 'approved_at', 'created_at'
            ]);

        // Filtro por visibilidade
        if ($request->has('is_public')) {
            $query->where('is_visible_to_public', $request->boolean('is_public'));
        }

        // Filtro por status de aprovação
        if ($request->has('is_approved')) {
            if ($request->boolean('is_approved')) {
                $query->whereNotNull('approved_at');
            } else {
                $query->whereNull('approved_at');
            }
        }

        $products = $query->paginate(20);

        return response()->json($products);
    }

    /**
     * Aprovar um produto para visibilidade pública
     */
    public function approveProduct(Request $request, Product $product): JsonResponse
    {
        if ($product->is_visible_to_public && $product->approved_at) {
            return response()->json([
                'message' => 'O produto já foi aprovado.',
            ], 400);
        }

        $oldValue = $product->is_visible_to_public;
        $product->update([
            'is_visible_to_public' => true,
            'approved_at'          => now(),
        ]);

        AdminAuditLog::logAction(
            auth()->user(),
            'product_approved',
            'Product',
            $product->id,
            ['is_visible_to_public' => $oldValue, 'approved_at' => null],
            ['is_visible_to_public' => true, 'approved_at' => now()],
            $request->input('notes')
        );

        return response()->json([
            'message' => "Produto '{$product->name}' foi aprovado e agora está visível para o público.",
            'product' => $product,
        ]);
    }

    /**
     * Rejeitar um produto (impedir visibilidade pública)
     */
    public function rejectProduct(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        AdminAuditLog::logAction(
            auth()->user(),
            'product_rejected',
            'Product',
            $product->id,
            ['is_visible_to_public' => $product->is_visible_to_public],
            ['is_visible_to_public' => false],
            $request->reason
        );

        // Não atualiza o produto, apenas registra a rejeição
        return response()->json([
            'message' => "Produto '{$product->name}' foi rejeitado. Motivo: {$request->reason}",
            'product' => $product,
        ]);
    }

    /**
     * Aprovar todos os produtos de uma loja para visibilidade pública
     */
    public function approveStoreProducts(Request $request, Store $store): JsonResponse
    {
        $request->validate([
            'approve_all' => 'required|boolean',
        ]);

        if (!$request->boolean('approve_all')) {
            return response()->json([
                'message' => 'Confirmação necessária para aprovar todos os produtos da loja.',
            ], 422);
        }

        $updateCount = $store->products()
            ->where('is_visible_to_public', false)
            ->update([
                'is_visible_to_public' => true,
                'approved_at'          => now(),
            ]);

        AdminAuditLog::logAction(
            auth()->user(),
            'store_products_bulk_approved',
            'Store',
            $store->id,
            [],
            ['count' => $updateCount],
            "{$updateCount} produtos aprovados em lote"
        );

        return response()->json([
            'message' => "{$updateCount} produtos da loja '{$store->name}' foram aprovados.",
            'updated_count' => $updateCount,
        ]);
    }

    /**
     * Revogar aprovação de um produto (volta a ficar oculto)
     */
    public function revokeApproval(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if (!$product->is_visible_to_public) {
            return response()->json([
                'message' => 'O produto já não está visível ao público.',
            ], 400);
        }

        $oldValue = $product->is_visible_to_public;
        $product->update([
            'is_visible_to_public' => false,
            'approved_at'          => null,
        ]);

        AdminAuditLog::logAction(
            auth()->user(),
            'product_approval_revoked',
            'Product',
            $product->id,
            ['is_visible_to_public' => $oldValue, 'approved_at' => $product->approved_at],
            ['is_visible_to_public' => false, 'approved_at' => null],
            $request->reason
        );

        return response()->json([
            'message' => "Aprovação do produto '{$product->name}' foi revogada.",
            'product' => $product,
        ]);
    }
}
