<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreNotification;
use App\Models\StoreVisibilityPurchase;
use App\Models\VisibilityPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminVisibilityController extends Controller
{
    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): JsonResponse
    {
        $now = now();

        $activeCount = StoreVisibilityPurchase::where('status', 'active')
            ->where('expires_at', '>', $now)
            ->count();

        $expiringIn7Days = StoreVisibilityPurchase::where('status', 'active')
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(7)])
            ->count();

        $revenueThisMonth = StoreVisibilityPurchase::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->where('status', 'active')
            ->sum('amount_paid');

        return response()->json([
            'active_count'       => $activeCount,
            'expiring_in_7_days' => $expiringIn7Days,
            'revenue_this_month' => (float) $revenueThisMonth,
        ]);
    }

    // ─── Paginated list ───────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $purchases = StoreVisibilityPurchase::with(['store', 'plan'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->whereHas('store', function ($sq) use ($request) {
                $sq->where('name', 'like', '%' . $request->search . '%');
            }))
            ->latest()
            ->paginate(30);

        return response()->json($purchases);
    }

    // ─── Store history ────────────────────────────────────────────────────────

    public function storeHistory(Store $store): JsonResponse
    {
        $purchases = $store->visibilityPurchases()
            ->with('plan')
            ->latest()
            ->get();

        return response()->json($purchases);
    }

    // ─── History by purchase ID ───────────────────────────────────────────────

    public function purchaseHistory(StoreVisibilityPurchase $purchase): JsonResponse
    {
        $history = StoreVisibilityPurchase::where('store_id', $purchase->store_id)
            ->with('plan')
            ->latest()
            ->get();
        return response()->json($history);
    }

    // ─── Update status ────────────────────────────────────────────────────────

    public function updateStatus(Request $request, StoreVisibilityPurchase $purchase): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending_payment,active,expired,cancelled',
        ]);
        $purchase->update($data);

        // If cancelling/expiring, remove store featured status if no other active plans
        if (in_array($data['status'], ['cancelled', 'expired'])) {
            $hasOtherActive = StoreVisibilityPurchase::where('store_id', $purchase->store_id)
                ->where('id', '!=', $purchase->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->exists();
            if (!$hasOtherActive) {
                $purchase->store->update(['is_featured' => false, 'visibility_position' => 0]);
            }
        }

        return response()->json($purchase->fresh('plan'));
    }

    // ─── Activate / create a visibility purchase for a store ─────────────────

    public function activate(Request $request, Store $store): JsonResponse
    {
        $data = $request->validate([
            'visibility_plan_id' => 'required|exists:visibility_plans,id',
            'starts_at'          => 'required|date',
            'expires_at'         => 'required|date|after:starts_at',
            'amount_paid'        => 'required|numeric|min:0',
            'payment_method'     => 'required|in:emola,mpesa,cash',
            'payment_reference'  => 'nullable|string|max:255',
            'notes'              => 'nullable|string',
        ]);

        $plan = VisibilityPlan::findOrFail($data['visibility_plan_id']);
        $invoiceNumber = 'INV-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $purchase = StoreVisibilityPurchase::create([
            'store_id'           => $store->id,
            'visibility_plan_id' => $plan->id,
            'starts_at'          => $data['starts_at'],
            'expires_at'         => $data['expires_at'],
            'amount_paid'        => $data['amount_paid'],
            'payment_method'     => $data['payment_method'],
            'payment_reference'  => $data['payment_reference'] ?? null,
            'notes'              => $data['notes'] ?? null,
            'status'             => 'active',
            'invoice_number'     => $invoiceNumber,
            'next_payment_at'    => $data['expires_at'],
        ]);

        // Update store: position_boost from plan, featured badge, expiry
        $store->update([
            'is_featured'           => (bool) $plan->is_featured_badge,
            'visibility_position'   => $plan->position_boost,
            'visibility_expires_at' => $data['expires_at'],
        ]);

        // Notify store owner
        StoreNotification::create([
            'user_id' => $store->user_id,
            'type'    => 'visibility_activated',
            'title'   => 'Visibilidade activada! 🎉',
            'body'    => "O plano {$plan->name} da sua loja '{$store->name}' foi activado. Válido até " . date('d/m/Y', strtotime($data['expires_at'])) . '. A sua loja já aparece em destaque na plataforma.',
            'data'    => [
                'purchase_id'    => $purchase->id,
                'invoice_number' => $invoiceNumber,
                'plan_name'      => $plan->name,
                'expires_at'     => $data['expires_at'],
            ],
        ]);

        return response()->json($purchase->load('plan', 'store'), 201);
    }

    // ─── Remove a visibility purchase ────────────────────────────────────────

    public function remove(StoreVisibilityPurchase $purchase): JsonResponse
    {
        $store = $purchase->store;

        $purchase->delete();

        // Remove store featured status if no other active plans remain
        $hasOtherActive = StoreVisibilityPurchase::where('store_id', $store->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();

        if (!$hasOtherActive) {
            $store->update([
                'is_featured'           => false,
                'visibility_position'   => 0,
                'visibility_expires_at' => null,
            ]);
        }

        // Notify store owner
        StoreNotification::create([
            'user_id' => $store->user_id,
            'type'    => 'visibility_removed',
            'title'   => 'Visibilidade removida',
            'body'    => "O plano de visibilidade da sua loja '{$store->name}' foi removido pelo administrador.",
            'data'    => ['store_id' => $store->id],
        ]);

        return response()->json(null, 204);
    }

    // ─── Send payment reminder ────────────────────────────────────────────────

    public function sendPaymentReminder(StoreVisibilityPurchase $purchase): JsonResponse
    {
        // Check if already notified in the last 7 days
        if ($purchase->payment_notified_at && $purchase->payment_notified_at->greaterThan(now()->subDays(7))) {
            return response()->json([
                'message' => 'Lembrete já enviado nos últimos 7 dias. Próximo envio disponível em ' .
                    $purchase->payment_notified_at->addDays(7)->format('d/m/Y') . '.',
            ], 422);
        }

        $store = $purchase->store;

        StoreNotification::create([
            'user_id' => $store->user_id,
            'type'    => 'visibility_reminder',
            'title'   => 'Lembrete de pagamento de visibilidade',
            'body'    => "O plano de visibilidade da sua loja '{$store->name}' expira em " .
                $purchase->expires_at->format('d/m/Y') . '. Por favor, renove para manter a sua visibilidade.',
            'data'    => [
                'purchase_id' => $purchase->id,
                'expires_at'  => $purchase->expires_at->toDateTimeString(),
            ],
        ]);

        $purchase->update(['payment_notified_at' => now()]);

        return response()->json(['message' => 'Lembrete enviado com sucesso.']);
    }
}
