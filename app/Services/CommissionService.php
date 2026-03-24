<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\CommissionPayout;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    private float $ratePerProduct;
    private string $payoutPhoneMpesa;
    private string $payoutPhoneEmola;

    public function __construct(
        private MpesaService $mpesaService,
        private EmolaService $emolaService,
    ) {
        $this->ratePerProduct = (float) config('services.commission.rate_per_product', 0.50);
        $this->payoutPhoneMpesa = config('services.commission.payout_phone_mpesa', '258840442932');
        $this->payoutPhoneEmola = config('services.commission.payout_phone_emola', '258973157227');
    }

    /**
     * Registar comissões quando um pedido é pago
     * 0.50 MZN × quantidade de cada produto = comissão total
     */
    public function registerOrderCommissions(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->storeOrders as $storeOrder) {
                foreach ($storeOrder->items as $item) {
                    $commissionAmount = $this->ratePerProduct * $item->quantity;

                    Commission::create([
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'store_id' => $storeOrder->store_id,
                        'quantity' => $item->quantity,
                        'rate' => $this->ratePerProduct,
                        'amount' => $commissionAmount,
                        'status' => 'pending',
                    ]);
                }
            }
        });

        Log::info('Comissões registadas', ['order_id' => $order->id]);

        // Verificar se acumulou suficiente para pagar automaticamente
        if (config('services.commission.auto_payout', true)) {
            $this->checkAndAutoPayout();
        }
    }

    /**
     * Verifica se há comissões suficientes e processa pagamento automático
     */
    public function checkAndAutoPayout(): void
    {
        $pendingAmount = Commission::where('status', 'pending')->sum('amount');
        $threshold = (float) config('services.commission.payout_threshold', 100);

        if ($pendingAmount >= $threshold) {
            $method = config('services.commission.payout_method', 'mpesa');
            $this->processPayout($method);
        }
    }

    /**
     * Processa o pagamento das comissões pendentes para o proprietário da plataforma
     */
    public function processPayout(string $method): array
    {
        $pendingCommissions = Commission::where('status', 'pending')->get();
        $totalAmount = $pendingCommissions->sum('amount');

        if ($totalAmount <= 0) {
            return ['success' => false, 'message' => 'Sem comissões pendentes.'];
        }

        $phone = $method === 'mpesa' ? $this->payoutPhoneMpesa : $this->payoutPhoneEmola;
        $reference = 'BC-COM-' . time();

        // Criar registo de payout
        $payout = CommissionPayout::create([
            'total_amount' => $totalAmount,
            'total_commissions' => $pendingCommissions->count(),
            'payment_method' => $method,
            'recipient_phone' => $phone,
            'status' => 'processing',
            'payment_reference' => $reference,
        ]);

        // Marcar comissões como em processamento
        Commission::where('status', 'pending')->update([
            'status' => 'processing',
            'payment_reference' => $reference,
        ]);

        // Processar pagamento via gateway
        $description = "Comissão Beconnect - {$pendingCommissions->count()} produtos vendidos";

        $result = match ($method) {
            'mpesa' => $this->mpesaService->c2bPayment($phone, $totalAmount, $reference, 'BECONNECT-COM'),
            'emola' => $this->emolaService->initiatePayment($phone, $totalAmount, $reference, $description),
            default => ['success' => false, 'message' => 'Método inválido.'],
        };

        if ($result['success'] ?? false) {
            $payout->update([
                'status' => 'completed',
                'transaction_id' => $result['transaction_id'] ?? null,
                'processed_at' => now(),
            ]);

            Commission::where('payment_reference', $reference)->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            Log::info('Comissão paga', ['amount' => $totalAmount, 'method' => $method, 'phone' => $phone]);

            return [
                'success' => true,
                'message' => "Comissão de {$totalAmount} MZN paga via {$method} para {$phone}.",
                'amount' => $totalAmount,
                'payout_id' => $payout->id,
            ];
        } else {
            $payout->update(['status' => 'failed']);
            Commission::where('payment_reference', $reference)->update(['status' => 'pending']);

            Log::error('Falha no pagamento de comissão', $result);

            return [
                'success' => false,
                'message' => 'Falha ao processar pagamento de comissão: ' . ($result['message'] ?? 'Erro desconhecido'),
            ];
        }
    }

    /**
     * Resumo das comissões para o admin
     */
    public function getSummary(): array
    {
        return [
            'pending_amount' => Commission::where('status', 'pending')->sum('amount'),
            'pending_count' => Commission::where('status', 'pending')->count(),
            'total_paid' => Commission::where('status', 'paid')->sum('amount'),
            'total_products_comissioned' => Commission::sum('quantity'),
            'rate_per_product' => $this->ratePerProduct,
            'payout_phone_mpesa' => $this->payoutPhoneMpesa,
            'payout_phone_emola' => $this->payoutPhoneEmola,
        ];
    }
}
