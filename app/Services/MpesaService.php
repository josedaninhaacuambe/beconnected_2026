<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    private string $apiUrl;
    private string $publicKey;
    private string $apiKey;
    private string $serviceProviderCode;

    public function __construct()
    {
        $this->apiUrl = config('services.mpesa.api_url');
        $this->publicKey = config('services.mpesa.public_key');
        $this->apiKey = config('services.mpesa.api_key');
        $this->serviceProviderCode = config('services.mpesa.service_provider_code');
    }

    /**
     * C2B (Customer to Business) - Cobrança ao cliente via M-Pesa
     */
    public function c2bPayment(string $phoneNumber, float $amount, string $reference, string $thirdPartyReference): array
    {
        try {
            $bearerToken = $this->getBearerToken();

            $payload = [
                'input_TransactionReference' => $reference,
                'input_CustomerMSISDN' => $this->formatPhone($phoneNumber),
                'input_Amount' => number_format($amount, 2, '.', ''),
                'input_ThirdPartyReference' => $thirdPartyReference,
                'input_ServiceProviderCode' => $this->serviceProviderCode,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'Origin' => config('app.url'),
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/c2bPayment/singleStage/', $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['output_ResponseCode']) && $data['output_ResponseCode'] === 'INS-0') {
                return [
                    'success' => true,
                    'reference' => $reference,
                    'transaction_id' => $data['output_TransactionID'] ?? null,
                    'message' => 'Pagamento M-Pesa iniciado. Confirme no seu telemóvel.',
                ];
            }

            $errorMessage = $this->getMpesaErrorMessage($data['output_ResponseCode'] ?? 'UNKNOWN');
            Log::error('M-Pesa C2B failed', ['response' => $data]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'code' => $data['output_ResponseCode'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erro ao processar pagamento M-Pesa.'];
        }
    }

    /**
     * Verificar estado da transacção
     */
    public function checkStatus(string $reference): array
    {
        try {
            $bearerToken = $this->getBearerToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'Origin' => config('app.url'),
            ])->get($this->apiUrl . '/queryTransactionStatus/', [
                'input_QueryReference' => $reference,
                'input_ServiceProviderCode' => $this->serviceProviderCode,
                'input_ThirdPartyReference' => $reference,
            ]);

            $data = $response->json();

            return [
                'status' => $data['output_ResponseCode'] === 'INS-0' ? 'completed' : 'pending',
                'transaction_id' => $data['output_TransactionID'] ?? null,
                'response_code' => $data['output_ResponseCode'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error'];
        }
    }

    /**
     * Processar callback M-Pesa
     */
    public function handleCallback(array $data): array
    {
        if (($data['output_ResponseCode'] ?? '') === 'INS-0') {
            return [
                'success' => true,
                'reference' => $data['output_ThirdPartyReference'] ?? '',
                'transaction_id' => $data['output_TransactionID'] ?? '',
            ];
        }

        return ['success' => false];
    }

    private function getBearerToken(): string
    {
        // Encriptar API key com chave pública (RSA)
        $publicKeyFormatted = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($this->publicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        openssl_public_encrypt($this->apiKey, $encrypted, $publicKeyFormatted, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 9) {
            $phone = '258' . $phone;
        }
        return $phone;
    }

    private function getMpesaErrorMessage(string $code): string
    {
        return match ($code) {
            'INS-1' => 'Erro interno. Tente novamente.',
            'INS-5' => 'Saldo insuficiente.',
            'INS-6' => 'Transacção falhou.',
            'INS-9' => 'Pedido inválido.',
            'INS-10' => 'Saldo insuficiente na conta M-Pesa.',
            'INS-13' => 'Limite de transacção excedido.',
            'INS-14' => 'Conta suspensa.',
            'INS-15' => 'Conta inexistente.',
            'INS-16' => 'Organização sem plafond.',
            'INS-17' => 'Transacção não encontrada.',
            'INS-18' => 'Sessão expirada.',
            'INS-19' => 'Número de telefone inválido.',
            'INS-20' => 'Pedido duplicado.',
            'INS-21' => 'Limite diário excedido.',
            default => 'Erro ao processar pagamento M-Pesa.',
        };
    }
}
