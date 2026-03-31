<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\CommissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'payments';
    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(
        private string $reference,
        private string $transactionId,
        private array  $gatewayResponse,
    ) {}

    public function handle(CommissionService $commissionService): void
    {
        $payment = Payment::where('reference', $this->reference)->first();

        if (!$payment || $payment->status === 'completed') {
            return;
        }

        $payment->update([
            'status'           => 'completed',
            'transaction_id'   => $this->transactionId,
            'gateway_response' => $this->gatewayResponse,
            'paid_at'          => now(),
        ]);

        $order = $payment->order;
        $order->update([
            'payment_status' => 'paid',
            'status'         => 'confirmed',
        ]);

        $order->storeOrders()->update(['status' => 'confirmed']);

        $order->load('storeOrders.items');
        $commissionService->registerOrderCommissions($order);

        Log::info('Pagamento confirmado via queue', [
            'reference'      => $this->reference,
            'transaction_id' => $this->transactionId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha ao confirmar pagamento', [
            'reference' => $this->reference,
            'error'     => $exception->getMessage(),
        ]);
    }
}
