<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmolaService
{
    private string $apiUrl;
    private string $merchantId;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->apiUrl = config('services.emola.api_url');
        $this->merchantId = config('services.emola.merchant_id');
        $this->apiKey = config('services.emola.api_key');
        $this->apiSecret = config('services.emola.api_secret');
    }

    /**
     * Iniciar cobrança via eMola
     */
    public function initiatePayment(string $phoneNumber, float $amount, string $reference, string $description): array
    {
        try {
            $payload = [
                'merchant_id' => $this->merchantId,
                'reference' => $reference,
                'amount' => $amount,
                'msisdn' => $this->formatPhone($phoneNumber),
                'description' => $description,
                'callback_url' => route('payment.emola.callback'),
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/payments/initiate', $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'reference' => $reference,
                    'message' => 'Pedido de pagamento enviado para o seu eMola. Confirme no telemóvel.',
                    'data' => $response->json(),
                ];
            }

            Log::error('eMola payment failed', ['response' => $response->json()]);
            return [
                'success' => false,
                'message' => 'Falha ao iniciar pagamento eMola.',
            ];
        } catch (\Exception $e) {
            Log::error('eMola exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erro ao processar pagamento.'];
        }
    }

    /**
     * Verificar estado de pagamento
     */
    public function checkStatus(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ])->get($this->apiUrl . '/payments/' . $reference);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['status'] ?? 'pending',
                    'transaction_id' => $data['transaction_id'] ?? null,
                ];
            }

            return ['status' => 'unknown'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Processar callback do eMola
     */
    public function handleCallback(array $data): array
    {
        // Validar assinatura do callback
        $signature = $data['signature'] ?? '';
        $expectedSignature = hash_hmac('sha256', $data['reference'] . $data['amount'], $this->apiSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('eMola callback signature mismatch', $data);
            return ['success' => false];
        }

        if (($data['status'] ?? '') === 'success') {
            return [
                'success' => true,
                'reference' => $data['reference'],
                'transaction_id' => $data['transaction_id'],
            ];
        }

        return ['success' => false];
    }

    private function getAccessToken(): string
    {
        $response = Http::post($this->apiUrl . '/auth/token', [
            'merchant_id' => $this->merchantId,
            'api_key' => $this->apiKey,
        ]);

        return $response->json('access_token', '');
    }

    private function formatPhone(string $phone): string
    {
        // Garantir formato Mozambique: 258XXXXXXXXX
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 9) {
            $phone = '258' . $phone;
        }
        return $phone;
    }
}
