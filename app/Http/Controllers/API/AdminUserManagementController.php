<?php

namespace App\Http\Controllers\API;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AdminUserManagementController extends Controller
{
    /**
     * Listar todos os usuários do sistema
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::select([
            'id', 'name', 'email', 'phone', 'role',
            'email_verified_at', 'is_active', 'created_at', 'updated_at'
        ]);

        // Filtro por role (admin, store_owner, customer)
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filtro por status ativo/inativo
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filtro por email verificado
        if ($request->has('email_verified')) {
            if ($request->boolean('email_verified')) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        // Busca por nome ou email
        if ($request->has('search')) {
            $searchTerm = "%{$request->search}%";
            $query->where('name', 'like', $searchTerm)
                ->orWhere('email', 'like', $searchTerm)
                ->orWhere('phone', 'like', $searchTerm);
        }

        // Ordenação
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate(20);

        return response()->json($users);
    }

    /**
     * Obter detalhes de um usuário específico
     */
    public function show(User $user): JsonResponse
    {
        $details = [
            'user'           => $user->only(['id', 'name', 'email', 'phone', 'role', 'is_active', 'created_at']),
            'stores_owned'   => $user->stores()->count() ?? 0,
            'orders_placed'  => $user->orders()->count() ?? 0,
            'total_spent'    => $user->orders()
                ->where('status', 'completed')
                ->sum('total_price') ?? 0,
        ];

        // Se é dono de loja, incluir informações das lojas
        if ($user->role === 'store_owner') {
            $details['stores'] = $user->stores()
                ->select(['id', 'name', 'status', 'availability_type', 'is_visible_to_public'])
                ->get();
        }

        return response()->json($details);
    }

    /**
     * Desativar um usuário
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Não pode desativar sua própria conta.',
            ], 403);
        }

        $oldStatus = $user->is_active;
        $user->update(['is_active' => false]);

        AdminAuditLog::logAction(
            auth()->user(),
            'user_deactivated',
            'User',
            $user->id,
            ['is_active' => $oldStatus],
            ['is_active' => false],
            $request->input('reason')
        );

        return response()->json([
            'message' => "Usuário '{$user->name}' foi desativado.",
            'user'    => $user,
        ]);
    }

    /**
     * Reativar um usuário desativado
     */
    public function reactivate(User $user): JsonResponse
    {
        if ($user->is_active) {
            return response()->json([
                'message' => 'O usuário já está ativo.',
            ], 400);
        }

        $oldStatus = $user->is_active;
        $user->update(['is_active' => true]);

        AdminAuditLog::logAction(
            auth()->user(),
            'user_reactivated',
            'User',
            $user->id,
            ['is_active' => $oldStatus],
            ['is_active' => true]
        );

        return response()->json([
            'message' => "Usuário '{$user->name}' foi reativado.",
            'user'    => $user,
        ]);
    }

    /**
     * Obter estatísticas gerais de usuários
     */
    public function statistics(): JsonResponse
    {
        return response()->json([
            'total_users'        => User::count(),
            'total_admins'       => User::where('role', 'admin')->count(),
            'total_store_owners' => User::where('role', 'store_owner')->count(),
            'total_customers'    => User::where('role', 'customer')->count(),
            'active_users'       => User::where('is_active', true)->count(),
            'inactive_users'     => User::where('is_active', false)->count(),
            'email_verified'     => User::whereNotNull('email_verified_at')->count(),
            'email_not_verified' => User::whereNull('email_verified_at')->count(),
        ]);
    }
}
