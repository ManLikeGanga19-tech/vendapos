<?php

declare(strict_types=1);

use App\Domain\Payments\Gateways\DarajaGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Device tokens
    |--------------------------------------------------------------------------
    |
    | Terminals authenticate with one Sanctum token per device. Tokens expire
    | (architecture §10) and are rolled over by the refresh endpoint while
    | still valid; an expired token needs a staff sign-in on the terminal.
    |
    */

    'device_token_ttl_hours' => (int) env('VENDAPOS_DEVICE_TOKEN_TTL_HOURS', 12),

    // Abilities granted to a terminal's token.
    'device_abilities' => ['orders:sync', 'payments:initiate'],

    /*
    |--------------------------------------------------------------------------
    | API rate limits (requests per minute)
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'device' => (int) env('VENDAPOS_RATE_LIMIT_DEVICE', 200),
        'tenant' => (int) env('VENDAPOS_RATE_LIMIT_TENANT', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile money
    |--------------------------------------------------------------------------
    |
    | Each provider maps to a gateway class. With `fake` on, every provider
    | uses FakeGateway (tests, demos, local work without sandbox keys).
    | Callbacks reach {callback_base_url}/webhooks/payments/{provider}/{token}.
    |
    */

    'payments' => [
        'fake' => (bool) env('VENDAPOS_PAYMENTS_FAKE', false),
        // What FakeGateway's status query reports: pending, succeeded or failed.
        'fake_outcome' => env('VENDAPOS_PAYMENTS_FAKE_OUTCOME', 'pending'),
        'callback_base_url' => env('VENDAPOS_PAYMENTS_CALLBACK_URL', env('APP_URL')),
        'gateways' => [
            'mpesa_ke' => DarajaGateway::class,
            // 'airtel_ke' => AirtelGateway::class (slice E3)
        ],

        // Callbacks are accepted only from the provider's published addresses (CLAUDE.md §4).
        // Turn off only for local tunnels; requires TrustProxies to be right behind a load balancer.
        'enforce_callback_ips' => (bool) env('VENDAPOS_PAYMENTS_ENFORCE_CALLBACK_IPS', true),
        'callback_ips' => [
            // Safaricom's published Daraja callback addresses. Re-check against the Daraja portal before go-live.
            'mpesa_ke' => [
                '196.201.214.200', '196.201.214.206', '196.201.213.114', '196.201.214.207',
                '196.201.214.208', '196.201.213.44', '196.201.212.127', '196.201.212.138',
                '196.201.212.129', '196.201.212.136', '196.201.212.74', '196.201.212.69',
            ],
            'airtel_ke' => [],
        ],

        // Callbacks per minute per source IP.
        'callback_rate_limit' => (int) env('VENDAPOS_PAYMENTS_CALLBACK_RATE_LIMIT', 600),
    ],

    // Business days are counted here; storage stays UTC (CLAUDE.md §4).
    'business_timezone' => 'Africa/Nairobi',

    // Largest accepted API body, in KB (architecture §5).
    'api_body_limit_kb' => (int) env('VENDAPOS_API_BODY_LIMIT_KB', 64),

];
