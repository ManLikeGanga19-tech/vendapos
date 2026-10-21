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
    | (both use Kenya's, as requested by Danamo Tech).
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
    | Company and legal pages
    |--------------------------------------------------------------------------
    |
    | Facts shown on the privacy policy and terms. Unknown values stay null
    | and render with a "TBC" marker. Both documents show a "draft" notice
    | until `reviewed` is true (after Danamo Tech's legal advisers sign off).
    |
    */

    'company' => [
        'name' => 'Danamo Tech Limited',
        'location' => 'Mombasa, Kenya',
        'registration_number' => env('COMPANY_REGISTRATION_NUMBER', 'PVT-J61587ER'),
        'kra_pin' => env('COMPANY_KRA_PIN', 'P052477504F'),
        'postal_address' => env('COMPANY_POSTAL_ADDRESS'),
        'odpc_registration' => env('COMPANY_ODPC_REGISTRATION'),
        'hosting_region' => env('COMPANY_HOSTING_REGION'),
        'privacy_email' => env('COMPANY_PRIVACY_EMAIL'),
    ],

    'legal' => [
        'updated' => '2026-10-21',
        'reviewed' => (bool) env('MARKETING_LEGAL_REVIEWED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | KRA eTIMS certification
    |--------------------------------------------------------------------------
    |
    | Stays false until KRA lists VendaPOS as an approved eTIMS integrator.
    | The compliance page then shows "Certified" instead of a TBC status.
    |
    */

    'etims_certified' => (bool) env('MARKETING_ETIMS_CERTIFIED', false),

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
    | Solution pages
    |--------------------------------------------------------------------------
    |
    | One page per business type at /{locale}/solutions/{key}, rendered from
    | pages/solutions/show.blade.php with copy in lang/{locale}/solutions.php.
    | Restaurants has its own bespoke page. `panel` rows feed the screen
    | illustration: amounts in KES cents (null = none) and a badge variant.
    |
    */

    'solutions' => [
        'bars' => [
            'icon' => 'beer',
            'panel' => ['icon' => 'receipt-text', 'badge' => 'pending', 'rows' => [
                'table3' => ['cents' => 160_000, 'state' => 'success'],
                'seat6' => ['cents' => 650_000, 'state' => 'success'],
                'table8' => ['cents' => 330_000, 'state' => 'coral'],
                'staff' => ['cents' => 25_000, 'state' => 'pending'],
            ]],
            'problems' => ['tabs' => 'receipt-text', 'pricing' => 'clock', 'shrinkage' => 'shield-check'],
            'features' => ['tabs' => 'receipt-text', 'bottles' => 'beer', 'happy' => 'timer', 'cover' => 'users'],
            'hardware' => ['till' => 'monitor', 'printer' => 'printer'],
        ],
        'salons' => [
            'icon' => 'scissors',
            'panel' => ['icon' => 'calendar', 'badge' => 'neutral', 'rows' => [
                'amina' => ['cents' => 350_000, 'state' => 'success'],
                'brian' => ['cents' => 80_000, 'state' => 'coral'],
                'halima' => ['cents' => 120_000, 'state' => 'neutral'],
                'fatma' => ['cents' => 1_500_000, 'state' => 'pending'],
            ]],
            'problems' => ['noshows' => 'calendar', 'commission' => 'hand-coins', 'packages' => 'package'],
            'features' => ['calendar' => 'calendar', 'timed' => 'timer', 'staff' => 'users', 'commission' => 'hand-coins'],
            'hardware' => ['tablet' => 'monitor', 'phone' => 'smartphone'],
        ],
        'shops' => [
            'icon' => 'store',
            'panel' => ['icon' => 'users', 'badge' => 'neutral', 'rows' => [
                'otieno' => ['cents' => 85_000, 'state' => 'success'],
                'hassan' => ['cents' => 470_000, 'state' => 'pending'],
                'grace' => ['cents' => 0, 'state' => 'neutral'],
            ]],
            'problems' => ['queue' => 'scan-barcode', 'deni' => 'users', 'stock' => 'package'],
            'features' => ['scan' => 'scan-barcode', 'keys' => 'layout-grid', 'deni' => 'users', 'reports' => 'trending-up'],
            'hardware' => ['scanner' => 'scan-barcode', 'printer' => 'printer'],
        ],
        'supermarkets' => [
            'icon' => 'shopping-cart',
            'panel' => ['icon' => 'shopping-cart', 'badge' => 'success', 'rows' => [
                'lane1' => ['cents' => 4_812_000, 'state' => 'success'],
                'lane2' => ['cents' => 3_955_000, 'state' => 'success'],
                'lane3' => ['cents' => 2_210_000, 'state' => 'success'],
                'lane4' => ['cents' => 3_640_000, 'state' => 'neutral'],
            ]],
            'problems' => ['queues' => 'scan-barcode', 'promos' => 'sparkles', 'cashups' => 'banknote'],
            'features' => ['scale' => 'scan-barcode', 'promos' => 'sparkles', 'lanes' => 'shopping-cart', 'loyalty' => 'users'],
            'hardware' => ['scanner' => 'scan-barcode', 'scale' => 'package', 'drawer' => 'banknote', 'display' => 'monitor'],
        ],
        'hardware' => [
            'icon' => 'hammer',
            'panel' => ['icon' => 'receipt-text', 'badge' => 'coral', 'rows' => [
                'cement' => ['cents' => 3_120_000, 'state' => null],
                'steel' => ['cents' => 1_440_000, 'state' => null],
                'nails' => ['cents' => 562_500, 'state' => null],
            ]],
            'problems' => ['units' => 'package', 'quotes' => 'receipt-text', 'credit' => 'id-card'],
            'features' => ['units' => 'package', 'quotes' => 'receipt-text', 'delivery' => 'send', 'contractors' => 'id-card'],
            'hardware' => ['scanner' => 'scan-barcode', 'a4' => 'printer'],
        ],
        'pharmacy' => [
            'icon' => 'pill',
            'panel' => ['icon' => 'pill', 'badge' => 'coral', 'rows' => [
                'a23' => ['cents' => null, 'state' => 'coral'],
                'b07' => ['cents' => null, 'state' => 'success'],
                'a11' => ['cents' => null, 'state' => 'pending'],
            ]],
            'problems' => ['expiry' => 'calendar', 'records' => 'receipt-text', 'signoff' => 'badge-check'],
            'features' => ['batches' => 'package', 'prescriptions' => 'receipt-text', 'register' => 'lock', 'insurance' => 'shield-check'],
            'hardware' => ['scanner' => 'scan-barcode', 'printer' => 'printer'],
        ],
        'liquor' => [
            'icon' => 'package',
            'panel' => ['icon' => 'shield-check', 'badge' => 'success', 'rows' => [
                'age' => ['cents' => null, 'state' => 'pending'],
                'hours' => ['cents' => null, 'state' => 'success'],
                'case' => ['cents' => 480_000, 'state' => 'coral'],
                'single' => ['cents' => 25_000, 'state' => null],
            ]],
            'problems' => ['age' => 'id-card', 'hours' => 'clock', 'bulk' => 'package'],
            'features' => ['age' => 'id-card', 'hours' => 'clock', 'cases' => 'package', 'stock' => 'warehouse'],
            'hardware' => ['scanner' => 'scan-barcode', 'printer' => 'printer'],
        ],
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
