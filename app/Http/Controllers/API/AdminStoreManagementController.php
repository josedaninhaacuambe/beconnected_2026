<?php

namespace App\Http\Controllers\API;

use App\Models\AdminAuditLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AdminStoreManagementController extends Controller
{
    /**
     * Listar todas as lojas com seus detalhes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Store::with('user:id,name,email,phone')
            ->select([
                'id', 'name', 'user_id', 'status',
                'availability_type', 'is_visible_to_public',
                'created_at', 'updated_at'
            ]);

        // Filtro por status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por tipo de disponibilidade
        if ($request->has('availability')) {
            $query->where('availability_type', $request->availability);
        }

        // Filtro por visibilidade pública
        if ($request->has('is_public')) {
            $query->where('is_visible_to_public', $request->boolean('is_public'));
        }

        // Busca por nome
        if ($request->has('search')) {
            $searchTerm = "%{$request->search}%";
            $query->where('name', 'like', $searchTerm)
                ->orWhereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                      ->orWhere('email', 'like', $searchTerm);
                });
        }

        $stores = $query->paginate(15);

        return response()->json($stores);
    }

    /**
     * Obter detalhes de uma loja específica
     */
    public function show(Store $store): JsonResponse
    {
        $store->load('user:id,name,email,phone');

        return response()->json([
            'store'        => $store,
            'products_count' => $store->products()->count(),
            'public_products' => $store->products()
                ->where('is_visible_to_public', true)
                ->count(),
            'orders_count' => $store->orders()->count(),
        ]);
    }

    /**
     * Atualizar tipo de disponibilidade da loja
     */
    public function updateAvailability(Request $request, Store $store): JsonResponse
    {
        $request->validate([
            'availability_type' => 'required|in:pos_only,virtual_only,both',
        ]);

        $oldValue = $store->availability_type;
        $newValue = $request->availability_type;

        $store->update(['availability_type' => $newValue]);

        // Log da ação
        AdminAuditLog::logAction(
            auth()->user(),
            'store_availability_updated',
            'Store',
            $store->id,
            ['availability_type' => $oldValue],
            ['availability_type' => $newValue],
            "Alterado de '{$oldValue}' para '{$newValue}'"
        );

        return response()->json([
            'message' => "Disponibilidade da loja '{$store->name}' atualizada para '{$newValue}'.",
            'store'   => $store,
        ]);
    }

    /**
     * Alternar visibilidade pública da loja
     */
    public function toggleVisibility(Request $request, Store $store): JsonResponse
    {
        $request->validate([
            'is_visible' => 'required|boolean',
        ]);

        $oldValue = $store->is_visible_to_public;
        $newValue = $request->boolean('is_visible');

        $store->update(['is_visible_to_public' => $newValue]);

        // Log da ação
        AdminAuditLog::logAction(
            auth()->user(),
            'store_visibility_toggled',
            'Store',
            $store->id,
            ['is_visible_to_public' => $oldValue],
            ['is_visible_to_public' => $newValue]
        );

        return response()->json([
            'message' => "Visibilidade da loja '{$store->name}' " . ($newValue ? 'ativada' : 'desativada') . '.',
            'store'   => $store,
        ]);
    }

    /**
     * Suspender uma loja
     */
    public function suspend(Request $request, Store $store): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $oldStatus = $store->status;
        $store->update(['status' => 'suspended']);

        AdminAuditLog::logAction(
            auth()->user(),
            'store_suspended',
            'Store',
            $store->id,
            ['status' => $oldStatus],
            ['status' => 'suspended'],
            $request->reason
        );

        return response()->json([
            'message' => "Loja '{$store->name}' foi suspensa.",
            'store'   => $store,
        ]);
    }

    /**
     * Reativar uma loja suspensa
     */
    public function reactivate(Store $store): JsonResponse
    {
        if ($store->status !== 'suspended') {
            return response()->json([
                'message' => 'A loja não está suspensa.',
            ], 400);
        }

        $oldStatus = $store->status;
        $store->update(['status' => 'active']);

        AdminAuditLog::logAction(
            auth()->user(),
            'store_reactivated',
            'Store',
            $store->id,
            ['status' => $oldStatus],
            ['status' => 'active']
        );

        return response()->json([
            'message' => "Loja '{$store->name}' foi reativada.",
            'store'   => $store,
        ]);
    }
}
