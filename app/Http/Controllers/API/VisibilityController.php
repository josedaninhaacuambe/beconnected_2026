<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreVisibilityPurchase;
use App\Models\VisibilityPlan;
use App\Services\PaymentService;
use App\Traits\ResolvesOwnerStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisibilityController extends Controller
{
    use ResolvesOwnerStore;

    public function __construct(private PaymentService $paymentService) {}

    public function plans(): JsonResponse
    {
        return response()->json(VisibilityPlan::where('is_active', true)->get());
    }

    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visibility_plan_id' => 'required|exists:visibility_plans,id',
            'payment_method' => 'required|in:emola,mpesa',
            'payment_phone' => 'required|string|max:20',
        ]);

        $store = $this->resolveOwnerStore($request);
        $plan = VisibilityPlan::findOrFail($validated['visibility_plan_id']);

        return DB::transaction(function () use ($validated, $store, $plan, $request) {
            $startsAt = now();
            $expiresAt = now()->addDays($plan->duration_days);

            $purchase = StoreVisibilityPurchase::create([
                'store_id' => $store->id,
                'visibility_plan_id' => $plan->id,
                'amount_paid' => $plan->price,
                'payment_method' => $validated['payment_method'],
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
            ]);

            // Registar pagamento (sem order_id, usar um order virtual)
            $paymentResult = $this->paymentService->initiateVisibilityPayment(
                $store,
                $plan->price,
                $validated['payment_method'],
                $validated['payment_phone'],
                $purchase->id
            );

            return response()->json([
                'message' => 'Solicitação enviada. Complete o pagamento para activar.',
                'purchase' => $purchase,
                'payment' => $paymentResult,
            ], 201);
        });
    }

    public function activatePurchase(StoreVisibilityPurchase $purchase): void
    {
        $plan = $purchase->plan;
        $purchase->store->update([
            'visibility_position' => $plan->position_boost,
            'visibility_expires_at' => $purchase->expires_at,
            'is_featured' => $plan->is_featured_badge,
        ]);
    }

    // Contratos a expirar nos próximos N dias (admin)
    public function expiringContracts(Request $request): JsonResponse
    {
        $days = (int) ($request->get('days', 7));

        $purchases = StoreVisibilityPurchase::with(['store.owner', 'plan'])
            ->where('status', 'active')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->orderBy('expires_at')
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'store_name'  => $p->store->name,
                'store_email' => $p->store->owner?->email,
                'plan_name'   => $p->plan->name,
                'expires_at'  => $p->expires_at,
                'days_left'   => (int) now()->diffInDays($p->expires_at, false),
            ]);

        return response()->json($purchases);
    }

    // Enviar notificação de renovação para a loja (admin)
    public function notifyRenewal(Request $request, StoreVisibilityPurchase $purchase): JsonResponse
    {
        $store = $purchase->store()->with('owner')->first();
        if ($store?->owner) {
            $store->owner->notify(new \App\Notifications\ContractExpiryNotification($purchase));
        }
        return response()->json(['message' => 'Notificação enviada.']);
    }
}
