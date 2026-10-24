<?php

declare(strict_types=1);

use App\Domain\Etims\Clients\FakeEtimsClient;
use App\Domain\Etims\Clients\NullEtimsClient;
use App\Domain\Payments\Gateways\DarajaGateway;
use App\Infrastructure\Sms\AfricasTalkingSmsClient;
use App\Infrastructure\Sms\FakeSmsClient;

return [

    /*
    |--------------------------------------------------------------------------
    | Device tokens
    |--------------------------------------------------------------------------
    |
    | Terminals authenticate with one Sanctum token per device. Tokens expire
    | (architecture §10) and are rolled over by the refresh endpoint while
    | still valid. A till that was closed or offline past expiry renews its
    | expired token within the grace window; after that it needs a staff
    | sign-in on the terminal.
    |
    */

    'device_token_ttl_hours' => (int) env('VENDAPOS_DEVICE_TOKEN_TTL_HOURS', 12),

    'device_token_grace_days' => (int) env('VENDAPOS_DEVICE_TOKEN_GRACE_DAYS', 7),

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

        // Expired-token renewal, per client IP and per claimed device ID,
        // counted atomically in Redis so every server shares one count.
        'token_renewal' => [
            'per_minute' => (int) env('VENDAPOS_RATE_LIMIT_TOKEN_RENEWAL_MINUTE', 10),
            'per_hour' => (int) env('VENDAPOS_RATE_LIMIT_TOKEN_RENEWAL_HOUR', 30),
            'redis_connection' => 'cache',
            'key_prefix' => 'rate:token-renewal:',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxies and the client IP
    |--------------------------------------------------------------------------
    |
    | Traffic arrives through Cloudflare. The client IP is taken from
    | CF-Connecting-IP only when the connection comes from a Cloudflare edge
    | range or from one of our own proxies (e.g. cloudflared, a load
    | balancer). The edge ranges are refreshed daily from the URL below; the
    | list here is the fallback, copied from it on 2026-10-24.
    |
    */

    'network' => [
        'cloudflare_ips_url' => 'https://api.cloudflare.com/client/v4/ips',

        'cloudflare_ips' => [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
        ],

        // Our own proxies in front of the app, comma-separated IPs or CIDRs. Empty: none.
        'trusted_proxies' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('VENDAPOS_TRUSTED_PROXIES', '')),
        ))),
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

        // payments:reconcile (every minute) for late or missing callbacks.
        'reconcile' => [
            // Ask the provider once the customer has had time to answer the prompt.
            'first_query_after_seconds' => 60,
            // Time out only if the provider still says "processing" this long after the push.
            'give_up_after_minutes' => 10,
            // A payment never pushed (queue down) fails after this, under the row lock.
            'never_pushed_after_minutes' => 10,
            // Older pending payments are left for manual review.
            'stop_after_hours' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | KRA eTIMS
    |--------------------------------------------------------------------------
    |
    | Every order gets an eTIMS status. A branch sends documents only once its
    | Pennant flag EtimsEnabled is on; until a certified client exists, `null`
    | makes such a branch's documents fail and retry, never count as sent.
    |
    */

    'etims' => [
        'client' => env('VENDAPOS_ETIMS_CLIENT', 'null'),
        'clients' => [
            'null' => NullEtimsClient::class,
            'fake' => FakeEtimsClient::class,
        ],
        // A worker holds a document this long while sending; longer than any client timeout.
        'lease_seconds' => 300,
        // After each failed attempt, then hourly (architecture §6.1).
        'retry_delays_seconds' => [30, 60, 120],
        'retry_every_seconds' => 3600,
        // About three days of hourly retries, then the document is marked failed for the Admin panel.
        'max_attempts' => 75,
        // Documents one RetryFailedEtims job sends per tenant per run.
        'retry_batch_size' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | Owner notices go by SMS through Africa's Talking (platform account).
    | Outside production the fake client only logs, unless set otherwise; the
    | username "sandbox" sends to AT's simulator.
    |
    */

    'sms' => [
        'client' => env('VENDAPOS_SMS_CLIENT', env('APP_ENV') === 'production' ? 'africastalking' : 'fake'),
        'clients' => [
            'africastalking' => AfricasTalkingSmsClient::class,
            'fake' => FakeSmsClient::class,
        ],
        'africastalking' => [
            'username' => env('AFRICASTALKING_USERNAME'),
            'api_key' => env('AFRICASTALKING_API_KEY'),
            // Empty until a sender ID is registered with the networks.
            'sender_id' => env('AFRICASTALKING_SENDER_ID'),
        ],
    ],

    // Business days are counted here; storage stays UTC (CLAUDE.md §4).
    'business_timezone' => 'Africa/Nairobi',

    // Largest accepted API body, in KB (architecture §5).
    'api_body_limit_kb' => (int) env('VENDAPOS_API_BODY_LIMIT_KB', 64),

];
