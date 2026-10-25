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
    | Staff sign-in
    |--------------------------------------------------------------------------
    |
    | Staff sign in with their PIN, checked on the till so it works offline.
    | Whoever is signed in is signed out after `idle_sign_out_seconds` without
    | a tap or key press. After `pin_max_attempts` wrong PINs in a row, that
    | person is locked on this till for `pin_lock_minutes`.
    |
    */

    'idle_sign_out_seconds' => (int) env('VENDAPOS_IDLE_SIGN_OUT_SECONDS', 60),

    'pin_max_attempts' => (int) env('VENDAPOS_PIN_MAX_ATTEMPTS', 5),

    'pin_lock_minutes' => (int) env('VENDAPOS_PIN_LOCK_MINUTES', 5),

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
