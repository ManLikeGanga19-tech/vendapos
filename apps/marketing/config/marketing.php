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
    | them with a visible "TBC" marker.
    |
    */

    'contact' => [
        'email' => env('MARKETING_CONTACT_EMAIL', 'sales@example.co.ke'),
        'phone' => env('MARKETING_CONTACT_PHONE', '+254 700 000 000'),
    ],

];
