<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // eMola - Moçambique
    'emola' => [
        'api_url' => env('EMOLA_API_URL', 'https://api.emola.co.mz/v1'),
        'merchant_id' => env('EMOLA_MERCHANT_ID'),
        'api_key' => env('EMOLA_API_KEY'),
        'api_secret' => env('EMOLA_API_SECRET'),
    ],

    // M-Pesa Vodacom Moçambique
    'mpesa' => [
        'api_url' => env('MPESA_API_URL', 'https://api.mpesa.co.mz/ipg/v1x'),
        'public_key' => env('MPESA_PUBLIC_KEY'),
        'api_key' => env('MPESA_API_KEY'),
        'service_provider_code' => env('MPESA_SERVICE_PROVIDER_CODE'),
    ],

    // Entrega
    'delivery' => [
        'base_fee' => env('DELIVERY_BASE_FEE', 50),
        'fee_per_km' => env('DELIVERY_FEE_PER_KM', 5),
    ],

    // Google OAuth
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL', '/api/auth/google/callback'),
    ],

    // Pexels API para imagens automáticas de produtos
    'pexels' => [
        'key' => env('PEXELS_API_KEY'),
    ],

    // Comissão do proprietário da plataforma
    'commission' => [
        'rate_per_product' => env('COMMISSION_RATE_PER_PRODUCT', 0.50), // 0.50 MZN por produto
        'payout_phone_mpesa' => env('COMMISSION_PHONE_MPESA', '258840442932'),
        'payout_phone_emola' => env('COMMISSION_PHONE_EMOLA', '258973157227'),
        'payout_method' => env('COMMISSION_PAYOUT_METHOD', 'mpesa'), // método principal de recebimento
        'auto_payout' => env('COMMISSION_AUTO_PAYOUT', true),
        'payout_threshold' => env('COMMISSION_PAYOUT_THRESHOLD', 100),
    ],

    // ─── Meilisearch (motor de pesquisa de produtos) ──────────────────────
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
        'key'  => env('MEILISEARCH_KEY', 'beconnect_meili_secret'),
    ],

];
