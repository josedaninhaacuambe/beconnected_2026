<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Store;

class PaymentService
{
    public function __construct(
        private EmolaService $emolaService,
        private MpesaService $mpesaService,
    ) {}

    public function initiate(Payment $payment, ?string $phoneNumber): array
    {
        if ($payment->method === 'cash_on_delivery') {
            return [
                'method' => 'cash_on_delivery',
                'message' => 'Pague em dinheiro na entrega.',
            ];
        }

        $reference = 'BC-PAY-' . $payment->id . '-' . time();
        $payment->update(['reference' => $reference]);

        $description = 'Pedido Beconnect #' . $payment->order->order_number;

        return match ($payment->method) {
            'emola' => $this->emolaService->initiatePayment(
                $phoneNumber,
                $payment->amount,
                $reference,
                $description
            ),
            'mpesa' => $this->mpesaService->c2bPayment(
                $phoneNumber,
                $payment->amount,
                $reference,
                $payment->order->order_number
            ),
            default => ['success' => false, 'message' => 'Método de pagamento inválido.'],
        };
    }

    public function initiateVisibilityPayment(Store $store, float $amount, string $method, string $phone, int $purchaseId): array
    {
        $reference = 'BC-VIS-' . $purchaseId . '-' . time();
        $description = 'Plano de Visibilidade Beconnect - ' . $store->name;

        return match ($method) {
            'emola' => $this->emolaService->initiatePayment($phone, $amount, $reference, $description),
            'mpesa' => $this->mpesaService->c2bPayment($phone, $amount, $reference, 'VIS-' . $purchaseId),
            default => ['success' => false, 'message' => 'Método inválido.'],
        };
    }
}
