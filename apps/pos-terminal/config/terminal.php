<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | VendaPOS API
    |--------------------------------------------------------------------------
    |
    | Set per environment at build time. The till has no field to change it,
    | so nobody at the counter can point it at another server.
    |
    */

    'api_url' => env('VENDAPOS_API_URL'),

    'http_timeout_seconds' => (int) env('VENDAPOS_HTTP_TIMEOUT', 10),

    'http_connect_timeout_seconds' => (int) env('VENDAPOS_HTTP_CONNECT_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Background sync
    |--------------------------------------------------------------------------
    |
    | Menu and staff are pulled this often while the till is paired. The token
    | is refreshed once fewer than `token_refresh_hours_left` hours remain.
    |
    */

    'sync_interval_seconds' => (int) env('VENDAPOS_SYNC_INTERVAL', 60),

    'token_refresh_hours_left' => (int) env('VENDAPOS_TOKEN_REFRESH_HOURS_LEFT', 3),

    /*
    |--------------------------------------------------------------------------
    | Window
    |--------------------------------------------------------------------------
    |
    | Kiosk: full screen, not closable, no developer tools. On for production
    | builds; development opens a normal window.
    |
    */

    'kiosk' => (bool) env('VENDAPOS_KIOSK', env('APP_ENV') === 'production'),

];
