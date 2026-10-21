<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Every public page is served under a locale prefix (/en, /sw). The first
    | entry is the fallback when a visitor's browser asks for neither.
    | Labels are shown in their own language next to a flag from public/flags
    | (both use Kenya's, as requested by DanamoTech).
    |
    */

    'locales' => [
        'en' => ['label' => 'English', 'flag' => 'ke', 'og' => 'en_KE'],
        'sw' => ['label' => 'Kiswahili', 'flag' => 'ke', 'og' => 'sw_KE'],
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
    | Demo request leads
    |--------------------------------------------------------------------------
    |
    | Where new-lead alerts are emailed, and how long leads are kept before
    | deletion (stated in the privacy notice under the form; TBC until legal
    | signs off on the period).
    |
    */

    'leads' => [
        'inbox' => env('MARKETING_LEADS_INBOX', 'sales@example.co.ke'),
        'retention_months' => 12,
        'retention_confirmed' => false,
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

    // Paying yearly charges this many months (10 = two months free).
    'annual_months_charged' => 10,

    // What each plan includes, row by row, for the comparison table:
    // true = included, false = not included, string = lang key under pricing.matrix.values.
    'plan_contents_confirmed' => false,

    'plan_matrix' => [
        'selling' => [
            'offline' => [true, true, true],
            'tills' => ['one', 'up_to_five', 'unlimited'],
            'mpesa' => [true, true, true],
            'cards' => [true, true, true],
            'split_bills' => [false, true, true],
            'tabs' => [false, true, true],
        ],
        'compliance' => [
            'etims' => [true, true, true],
            'vat' => [true, true, true],
            'audit' => [true, true, true],
        ],
        'operations' => [
            'stock' => [true, true, true],
            'batches' => [false, true, true],
            'transfers' => [false, true, true],
            'kds' => [false, true, true],
            'approvals' => [false, true, true],
        ],
        'management' => [
            'dashboard' => [true, true, true],
            'multi_branch' => [false, true, true],
            'erp' => [false, false, true],
        ],
        'support' => [
            'help' => [true, true, true],
            'priority' => [false, true, true],
            'manager' => [false, false, true],
        ],
    ],

    // Optional extras on any plan, in KES cents; `per` is a lang key under pricing.addons.per.
    'addons' => [
        'till' => ['cents' => 120_000, 'per' => 'till_month', 'icon' => 'monitor'],
        'kds' => ['cents' => 180_000, 'per' => 'screen_month', 'icon' => 'chef-hat'],
        'messaging' => ['cents' => 250_000, 'per' => 'bundle', 'icon' => 'mail'],
        'onsite' => ['cents' => 450_000, 'per' => 'visit', 'icon' => 'headset'],
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
