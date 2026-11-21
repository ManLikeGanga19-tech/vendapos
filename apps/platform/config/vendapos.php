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

    // After a refresh, the old token keeps working this long (or until the
    // new one is first used), so a lost reply cannot lock the till out.
    'device_token_overlap_minutes' => (int) env('VENDAPOS_DEVICE_TOKEN_OVERLAP_MINUTES', 10),

    // Sign-in with an authenticator app on the Admin and Owner panels.
    // Only local development may switch it off (App\Filament\TwoFactorRequirement).
    'require_mfa' => (bool) env('VENDAPOS_REQUIRE_MFA', true),

    // How long a one-time till pairing code works after it is shown.
    'pairing_code_minutes' => 15,

    // Abilities granted to a terminal's token.
    'device_abilities' => ['orders:sync', 'payments:initiate', 'staff:read', 'staff:pin'],

    /*
    |--------------------------------------------------------------------------
    | Real-time
    |--------------------------------------------------------------------------
    |
    | Where tills open their Reverb connection. This is the public address,
    | which in production differs from the one the app uses to publish
    | (REVERB_HOST). The key is Reverb's public app key, never the secret.
    |
    */

    'realtime' => [
        'host' => env('VENDAPOS_REALTIME_HOST', env('REVERB_HOST')),
        'port' => (int) env('VENDAPOS_REALTIME_PORT', env('REVERB_PORT', 443)),
        'scheme' => env('VENDAPOS_REALTIME_SCHEME', env('REVERB_SCHEME', 'https')),
    ],

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
            'subjects' => ['ip', 'device'],
            'redis_connection' => 'cache',
            'key_prefix' => 'rate:token-renewal:',
        ],

        // Pairing a till with a one-time code: per client IP, plus a ceiling
        // across all callers so many addresses together can't guess codes.
        'device_pairing' => [
            'per_minute' => (int) env('VENDAPOS_RATE_LIMIT_PAIRING_MINUTE', 5),
            'per_hour' => (int) env('VENDAPOS_RATE_LIMIT_PAIRING_HOUR', 20),
            'all_per_minute' => (int) env('VENDAPOS_RATE_LIMIT_PAIRING_ALL_MINUTE', 300),
            'all_per_hour' => (int) env('VENDAPOS_RATE_LIMIT_PAIRING_ALL_HOUR', 3000),
            'subjects' => ['ip', 'all'],
            'redis_connection' => 'cache',
            'key_prefix' => 'rate:device-pairing:',
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
    | Callbacks reach {callback_base_url}/webhooks/payments/{token}; Safaricom
    | refuses URLs containing words such as "mpesa", so keep the base URL free of them.
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

        // payments:check-mpesa (every 5 minutes): a typed code Safaricom has not confirmed after
        // this long, or a paid push no sale has claimed, becomes a check for the owner (T3d)...
        'typed_code_grace_minutes' => (int) env('VENDAPOS_TYPED_CODE_GRACE_MINUTES', 30),
        // ...who is told in one digest at most this often.
        'check_digest_minutes' => (int) env('VENDAPOS_MPESA_CHECK_DIGEST_MINUTES', 30),

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
            // After Safaricom answers 429 (too many requests), ask about that payment again only after this.
            'backoff_seconds' => 180,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tickets (table service)
    |--------------------------------------------------------------------------
    |
    | A ticket is paid once (Daniel, 2026-10-30): a till holds it on the server
    | before taking payment. The hold lapses after this long, so a till that
    | went away cannot keep the table from paying elsewhere.
    |
    */

    'tickets' => [
        'payment_hold_minutes' => (int) env('VENDAPOS_TICKET_HOLD_MINUTES', 15),
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

    /*
    |--------------------------------------------------------------------------
    | Suspension and support
    |--------------------------------------------------------------------------
    |
    | A suspended tenant's data is exported and deleted this many days after
    | suspension unless it is reactivated first. The support line is quoted
    | in owner notices and on the suspended page when set.
    |
    */

    'suspension' => [
        'purge_after_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant exports
    |--------------------------------------------------------------------------
    |
    | pg_dump archives of a tenant's schema, encrypted with VENDAPOS_EXPORT_KEY
    | (base64 of 32 random bytes, kept apart from APP_KEY and backed up: an
    | export cannot be read without it). Kept for the retention period; tax
    | records are expected to be kept about five years (to be confirmed).
    |
    */

    'exports' => [
        'key' => env('VENDAPOS_EXPORT_KEY'),
        'disk' => env('VENDAPOS_EXPORT_DISK', 'exports'),
        'retention_years' => 5,
        'pg_dump' => env('VENDAPOS_PG_DUMP', 'pg_dump'),
        'pg_restore' => env('VENDAPOS_PG_RESTORE', 'pg_restore'),
        'timeout_seconds' => 3600,
    ],

    'support' => [
        'phone' => env('VENDAPOS_SUPPORT_PHONE'),
        'email' => env('VENDAPOS_SUPPORT_EMAIL'),
    ],

    // Business days are counted here; storage stays UTC (CLAUDE.md §4).
    'business_timezone' => 'Africa/Nairobi',

    // Largest accepted API body, in KB (architecture §5).
    'api_body_limit_kb' => (int) env('VENDAPOS_API_BODY_LIMIT_KB', 64),

];
