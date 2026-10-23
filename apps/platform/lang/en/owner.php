<?php

declare(strict_types=1);

return [
    'auth' => [
        'username' => 'Username',
    ],

    'setup' => [
        'title' => 'Set up your account',
        'name' => 'Name',
        'username_help' => 'You will sign in with this. 3 to 30 characters: letters, numbers, dots and underscores.',
        'username_format' => 'Use 3 to 30 characters: letters, numbers, dots and underscores only.',
        'username_taken' => 'Someone in your business already uses this username.',
        'submit' => 'Save and continue',
        'link_closed' => 'This link has expired or has already been used. Ask VendaPOS support for a new one.',
    ],

    'dashboard' => [
        'branch' => 'Branch',
        'all_branches' => 'All branches',
        'sales' => 'Sales today',
        'vat' => 'Includes :vat VAT',
        'orders' => 'Orders',
        'voids_cancels' => 'Voids / cancels',
        'voids_cancels_help' => 'Voided after the sale / dropped before payment',
        'etims' => 'Not yet with KRA',
        'etims_help' => 'eTIMS documents still being sent',
        'etims_failed' => ':count could not be sent yet and will be retried automatically',
        'sales_by_hour' => 'Sales by hour today',
        'sales_kes' => 'Sales (KES)',
        'no_sales' => 'No sales yet today',
        'top_items' => 'Top items today',
        'item' => 'Item',
        'quantity' => 'Sold',
        'revenue' => 'Revenue',
        'unknown_item' => 'Item no longer on the menu',
    ],
];
