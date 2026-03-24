<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentConfirmation;
use App\Models\Payment;
use App\Services\CommissionService;
use App\Services\EmolaService;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private EmolaService $emolaService,
        private MpesaService $mpesaService,
        private CommissionService $commissionService,
    ) {}


    // Callback do gateway eMola
    public function emolaCallback(Request $request): JsonResponse
    {
        $result = $this->emolaService->handleCallback($request->all());

        if ($result['success']) {
            // Despachar confirmação de forma assíncrona — responde imediatamente ao gateway
            ProcessPaymentConfirmation::dispatch(
                $result['reference'],
                $result['transaction_id'],
                $request->all()
            );
        }

        return response()->json(['status' => 'received']);
    }

    // Callback do gateway M-Pesa
    public function mpesaCallback(Request $request): JsonResponse
    {
        $result = $this->mpesaService->handleCallback($request->all());

        if ($result['success']) {
            ProcessPaymentConfirmation::dispatch(
                $result['reference'],
                $result['transaction_id'],
                $request->all()
            );
        }

        return response()->json(['status' => 'received']);
    }

    // Verificar estado do pagamento
    public function checkStatus(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment->order);

        $status = match ($payment->method) {
            'emola' => $this->emolaService->checkStatus($payment->reference),
            'mpesa' => $this->mpesaService->checkStatus($payment->reference),
            default => ['status' => $payment->status],
        };

        return response()->json($status);
    }


    // Histórico de pagamentos do utilizador
    public function myPayments(Request $request): JsonResponse
    {
        $payments = Payment::with('order')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json($payments);
    }
}
