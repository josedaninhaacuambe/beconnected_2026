<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionPayout;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreEmployee;
use App\Models\StoreVisibilityPurchase;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(private CommissionService $commissionService) {}

    // ─── Gestão de Utilizadores ───────────────────────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $users = User::with(['province', 'city'])
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->when($request->search, fn($q) => $q->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%'))
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    /**
     * Admin promove utilizador a dono de loja
     */
    public function promoteToStoreOwner(Request $request, User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Não é possível alterar o role de um admin.'], 422);
        }

        $user->update(['role' => 'store_owner']);

        return response()->json([
            'message' => "'{$user->name}' foi promovido a Dono de Loja com sucesso.",
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Admin rebaixa utilizador a customer
     */
    public function demoteToCustomer(User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Não é possível alterar o role de um admin.'], 422);
        }

        $user->update(['role' => 'customer']);

        return response()->json([
            'message' => "'{$user->name}' foi rebaixado a Cliente.",
            'user' => $user->fresh(),
        ]);
    }

    public function toggleUserStatus(User $user): JsonResponse
    {
        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'activada' : 'suspensa';
        return response()->json(['message' => "Conta {$status}.", 'user' => $user->fresh()]);
    }

    // ─── Gestão de Lojas ─────────────────────────────────────────────────────

    public function stores(Request $request): JsonResponse
    {
        $stores = Store::with(['owner', 'category', 'province', 'city'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->latest()
            ->paginate(20);

        return response()->json($stores);
    }

    public function approveStore(Store $store): JsonResponse
    {
        $store->update(['status' => 'active']);
        return response()->json(['message' => "Loja '{$store->name}' aprovada."]);
    }

    public function rejectStore(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate(['reason' => 'nullable|string']);
        $store->update(['status' => 'rejected']);
        return response()->json(['message' => "Loja '{$store->name}' rejeitada."]);
    }

    public function suspendStore(Store $store): JsonResponse
    {
        $store->update(['status' => 'suspended']);
        return response()->json(['message' => "Loja '{$store->name}' suspensa."]);
    }

    public function updateStore(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'phone'       => 'nullable|string|max:20',
            'whatsapp'    => 'nullable|string|max:20',
            'address'     => 'nullable|string|max:500',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
            'logo'        => 'nullable|image|max:2048',
            'banner'      => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('stores/logos', 'public');
        }
        if ($request->hasFile('banner')) {
            $validated['banner'] = $request->file('banner')->store('stores/banners', 'public');
        }

        $store->update($validated);

        return response()->json($store->fresh()->load(['category', 'province', 'city']));
    }

    // ─── Dashboard de Comissões ───────────────────────────────────────────────

    public function commissionDashboard(): JsonResponse
    {
        $stats = [
            'total_earned' => Commission::where('status', 'paid')->sum('amount'),
            'pending_amount' => Commission::where('status', 'pending')->sum('amount'),
            'total_products_sold' => Commission::sum('quantity'),
            'total_commissions' => Commission::count(),
            'last_payout' => CommissionPayout::where('status', 'completed')->latest()->first(),
            'monthly_breakdown' => Commission::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total, SUM(quantity) as products')
                ->where('status', 'paid')
                ->groupBy('year', 'month')
                ->orderByDesc('year')->orderByDesc('month')
                ->limit(12)
                ->get(),
        ];

        return response()->json($stats);
    }

    public function commissions(Request $request): JsonResponse
    {
        $commissions = Commission::with(['order.user', 'store'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(50);

        return response()->json($commissions);
    }

    /**
     * Processar pagamento manual das comissões pendentes
     */
    public function processCommissionPayout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:emola,mpesa',
        ]);

        $pendingAmount = Commission::where('status', 'pending')->sum('amount');
        $threshold = config('services.commission.payout_threshold', 100);

        if ($pendingAmount < $threshold) {
            return response()->json([
                'message' => "Montante pendente ({$pendingAmount} MZN) abaixo do mínimo ({$threshold} MZN) para pagamento.",
                'pending_amount' => $pendingAmount,
            ], 422);
        }

        $result = $this->commissionService->processPayout($validated['payment_method']);

        return response()->json($result);
    }

    public function payoutHistory(): JsonResponse
    {
        return response()->json(CommissionPayout::latest()->paginate(20));
    }

    // ─── Gestão de Pedidos (admin) ────────────────────────────────────────────

    public function allOrders(Request $request): JsonResponse
    {
        $orders = Order::with(['user', 'storeOrders.store', 'delivery'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->store_id, fn($q) => $q->whereHas('storeOrders', fn($sq) => $sq->where('store_id', $request->store_id)))
            ->when($request->search, fn($q) => $q->where(function ($sq) use ($request) {
                $sq->where('order_number', 'like', '%' . $request->search . '%')
                   ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%' . $request->search . '%')
                       ->orWhere('email', 'like', '%' . $request->search . '%'));
            }))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(30);

        return response()->json($orders);
    }

    public function resolveOrder(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'admin_note'  => 'nullable|string',
            'status'      => 'nullable|string',
            'refund_flag' => 'nullable|boolean',
        ]);

        $data['resolved_at'] = now();
        $order->update($data);

        return response()->json($order->fresh(['user', 'storeOrders.store', 'delivery']));
    }

    public function alertsCount(): JsonResponse
    {
        $expiringVisibility = StoreVisibilityPurchase::where('status', 'active')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->count();

        $unresolvedOrders = Order::where('refund_flag', true)
            ->whereNull('resolved_at')
            ->count();

        return response()->json([
            'total'               => $expiringVisibility + $unresolvedOrders,
            'expiring_visibility' => $expiringVisibility,
            'unresolved_orders'   => $unresolvedOrders,
        ]);
    }

    // ─── Estatísticas gerais ─────────────────────────────────────────────────

    public function overview(): JsonResponse
    {
        // Cache for 30s — 8 separate queries → single cached result
        $data = \Illuminate\Support\Facades\Cache::remember('admin_overview', 30, function () {
            $userStats = User::selectRaw('
                COUNT(*) as total_users,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today,
                SUM(CASE WHEN role = "store_owner" THEN 1 ELSE 0 END) as total_store_owners
            ')->first();

            $storeStats = Store::selectRaw('
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_stores
            ')->first();

            $commissionStats = Commission::selectRaw('
                SUM(CASE WHEN status = "pending" THEN amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END) as paid_amount
            ')->first();

            return [
                'total_users'         => (int) ($userStats->total_users ?? 0),
                'active_stores'       => (int) ($storeStats->active_stores ?? 0),
                'orders_today'        => \App\Models\Order::whereDate('created_at', today())->count(),
                'pending_commissions' => (float) ($commissionStats->pending_amount ?? 0),
                'paid_commissions'    => (float) ($commissionStats->paid_amount ?? 0),
                'new_users_today'     => (int) ($userStats->new_today ?? 0),
                'total_store_owners'  => (int) ($userStats->total_store_owners ?? 0),
                'pending_stores'      => Store::with(['owner', 'city'])
                                            ->where('status', 'pending')
                                            ->latest()
                                            ->limit(10)
                                            ->get(),
            ];
        });

        return response()->json($data);
    }

    // ─── Funcionários de todas as lojas (histórico permanente) ───────────────
    public function storeEmployees(Request $request): JsonResponse
    {
        $query = StoreEmployee::with([
            'user:id,name,email,phone,created_at',
            'store:id,name,slug',
            'addedBy:id,name',
        ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn($u) =>
                    $u->where('name',  'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                );
            });
        }

        if ($storeId = $request->integer('store_id')) {
            $query->where('store_id', $storeId);
        }

        if ($request->filled('active')) {
            $query->where('is_active', (bool) $request->integer('active'));
        }

        $employees = $query->orderByDesc('created_at')->paginate(50);

        return response()->json($employees);
    }
}
