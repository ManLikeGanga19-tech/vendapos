<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Every public page is served under a locale prefix (/en, /sw). The first
    | entry is the fallback when a visitor's browser asks for neither.
    | Labels are shown in their own language in the language switcher.
    |
    */

    'locales' => [
        'en' => 'English',
        'sw' => 'Kiswahili',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public contact details
    |--------------------------------------------------------------------------
    |
    | Placeholders until the real sales line and inbox exist; the site renders
    | them with a visible "TBC" marker until `confirmed` is true.
    |
    */

    'contact' => [
        'confirmed' => (bool) env('MARKETING_CONTACT_CONFIRMED', false),
        'email' => env('MARKETING_CONTACT_EMAIL', 'sales@example.co.ke'),
        'phone' => env('MARKETING_CONTACT_PHONE', '+254 700 000 000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Single source for every page that shows prices. Monthly prices are in
    | KES cents (null = quoted per customer). Until `prices_confirmed` is true
    | they render with a visible "TBC" marker.
    |
    */

    'prices_confirmed' => false,

    'plans' => [
        'starter' => ['monthly_cents' => 349_900, 'popular' => false],
        'business' => ['monthly_cents' => 799_900, 'popular' => true],
        'enterprise' => ['monthly_cents' => null, 'popular' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Headline figures
    |--------------------------------------------------------------------------
    |
    | Placeholders only. Set `confirmed` once a figure is measured and can be
    | defended publicly; unconfirmed figures render with a "TBC" marker.
    |
    */

    'stats' => [
        'volume' => ['value' => 'KES 0B+', 'confirmed' => false],
        'uptime' => ['value' => '99.9%', 'confirmed' => false],
        'mpesa' => ['value' => '0.0 s', 'confirmed' => false],
        'branches' => ['value' => '0+', 'confirmed' => false],
    ],

];
