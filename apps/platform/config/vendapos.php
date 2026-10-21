<?php

declare(strict_types=1);

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
            // 'mpesa_ke' => DarajaGateway::class (slice E2), 'airtel_ke' => AirtelGateway::class (E3)
        ],
    ],

    // Business days are counted here; storage stays UTC (CLAUDE.md §4).
    'business_timezone' => 'Africa/Nairobi',

    // Largest accepted API body, in KB (architecture §5).
    'api_body_limit_kb' => (int) env('VENDAPOS_API_BODY_LIMIT_KB', 64),

];
