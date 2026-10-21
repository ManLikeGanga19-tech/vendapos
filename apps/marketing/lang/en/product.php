<?php

return [
    'meta' => [
        'title' => 'Product',
        'description' => 'The VendaPOS till, M-Pesa and card payments, KRA eTIMS, offline sync, inventory, multi-branch reporting, staff controls and kitchen display — in one system.',
    ],

    'hero' => [
        'eyebrow' => 'Product',
        'title' => 'Everything your counter, kitchen and head office need.',
        'lead' => 'VendaPOS brings the till, payments, KRA eTIMS, stock and every branch together in one system that keeps working when the internet doesn’t.',
        'primary_cta' => 'Book a demo',
        'secondary_cta' => 'See pricing',
    ],

    'dashboard' => [
        'label' => 'Illustration of the VendaPOS owner dashboard',
        'title' => 'Today, all branches',
        'total' => 'Gross sales',
        'live' => 'Live',
        'branches' => [
            'cbd' => 'Mombasa CBD',
            'nyali' => 'Nyali',
            'diani' => 'Diani',
        ],
        'tills' => ':count tills',
        'syncing' => 'syncing',
        'recent' => 'Latest M-Pesa payments',
    ],

    'jump' => 'Jump to a feature',

    'sections' => [
        'till' => [
            'nav' => 'Till',
            'eyebrow' => 'The till',
            'title' => 'A fast till your staff learn in minutes.',
            'body' => 'Big touch targets, quick keys and barcode scanning keep queues moving. Each business type gets the screen it needs: tables for restaurants, tabs for bars, bookings for salons, lanes and scales for supermarkets.',
            'points' => [
                'scan' => 'Barcode and weighing-scale codes',
                'keys' => 'Quick keys and item modifiers',
                'split' => 'Split bills, holds and open tabs',
                'pin' => 'Fast PIN login for every cashier',
            ],
            'photo_alt' => 'A bartender preparing drinks behind a well-stocked bar counter',
        ],
        'payments' => [
            'nav' => 'Payments',
            'eyebrow' => 'Payments',
            'title' => 'M-Pesa, cards and cash, reconciled for you.',
            'body' => 'Send an STK push and the sale closes itself when Safaricom confirms. Every payment is double-checked with Daraja, so a missed confirmation never leaves a till short.',
            'points' => [
                'stk' => 'M-Pesa STK Push from the till',
                'confirm' => 'Automatic confirmation and status checks',
                'cards' => 'Cards through Pesapal and DPO',
                'cash' => 'Cash with change and shift cash-ups',
            ],
        ],
        'etims' => [
            'nav' => 'eTIMS',
            'eyebrow' => 'KRA eTIMS',
            'title' => 'Compliant receipts, without the paperwork.',
            'body' => 'Every sale is sent to KRA eTIMS in the background and the receipt carries its verification QR code. Sales made offline are queued and sent automatically when you reconnect.',
            'points' => [
                'vat' => 'Standard 16%, zero-rated and exempt items',
                'qr' => 'QR verification on every receipt',
                'offline' => 'Offline sales sent automatically on reconnect',
                'retry' => 'Failed submissions retried and flagged for review',
            ],
        ],
        'offline' => [
            'nav' => 'Offline',
            'eyebrow' => 'Offline-first',
            'title' => 'Built for the day the fibre gets cut.',
            'body' => 'Each till keeps its own local database. Sales, receipts and cash-ups carry on without internet, then sync in small batches when the connection returns, with no duplicate orders.',
            'points' => [
                'local' => 'Local database on every till',
                'batches' => 'Automatic sync in small batches',
                'ids' => 'Unique IDs prevent duplicate orders',
                'status' => 'Clear online and offline status on screen',
            ],
        ],
        'stock' => [
            'nav' => 'Inventory',
            'eyebrow' => 'Inventory',
            'title' => 'Know exactly what is on the shelf.',
            'body' => 'Track stock in the units you sell: pieces, metres, kilograms, bags or cases. Batch tracking sells the oldest stock first, and low-stock alerts tell you what to reorder.',
            'points' => [
                'units' => 'Units of measure and conversions',
                'batches' => 'Batch and expiry, first-expiry-first-out',
                'transfers' => 'Transfers between branches',
                'alerts' => 'Supplier costs and low-stock alerts',
            ],
            'photo_alt' => 'Shelves stacked high with stock in a hardware store',
        ],
        'branches' => [
            'nav' => 'Branches',
            'eyebrow' => 'Multi-branch',
            'title' => 'Every branch, live, from your phone.',
            'body' => 'Compare sales, stock and staff across all your outlets as they happen, and drill into any branch from the owner dashboard.',
            'points' => [
                'live' => 'Live sales by branch',
                'reports' => 'Consolidated reports',
                'roles' => 'Access per branch and role',
                'devices' => 'Works on phone, tablet and desktop',
            ],
            'photo_alt' => 'A shopping trolley in a supermarket aisle',
        ],
        'staff' => [
            'nav' => 'Staff',
            'eyebrow' => 'Staff controls',
            'title' => 'Protect your margins at every till.',
            'body' => 'Give each person the access their role needs. Voids and refunds need a supervisor, and every cash drawer opening is logged.',
            'points' => [
                'roles' => 'Roles and PINs for every staff member',
                'approvals' => 'Supervisor approval for voids and refunds',
                'cashup' => 'Shift cash-ups with variances',
                'audit' => 'Full audit trail',
            ],
            'photo_alt' => 'A pharmacist explaining a prescription to a customer',
        ],
        'kitchen' => [
            'nav' => 'Kitchen',
            'eyebrow' => 'Kitchen display',
            'title' => 'Orders reach the kitchen the moment they’re placed.',
            'body' => 'Tickets appear on kitchen and bar screens in real time, by station. Cooks bump them when ready, and front of house sees the status.',
            'points' => [
                'routing' => 'Routing by station',
                'timers' => 'Live ticket timers',
                'bump' => 'Bump and recall',
                'browser' => 'Runs on any screen with a browser',
            ],
        ],
    ],

    'stk' => [
        'label' => 'Illustration of an M-Pesa payment on the till',
        'amount' => 'Table 04 · :amount',
        'steps' => [
            'sent' => 'Payment request sent to 0712 ••• 678',
            'pin' => 'Customer enters their M-Pesa PIN',
            'confirmed' => 'Confirmed by Safaricom · receipt printed',
        ],
    ],

    'receipt' => [
        'label' => 'Illustration of a VendaPOS receipt with its KRA eTIMS QR code',
        'business' => 'Pwani Café, Mombasa',
        'items' => [
            'coffee' => 'Coffee, large',
            'mandazi' => 'Mandazi (2)',
            'water' => 'Water 500 ml',
        ],
        'vat' => 'VAT 16%',
        'total' => 'Total',
        'scan' => 'Scan to verify with KRA',
    ],

    'sync' => [
        'label' => 'Diagram of how offline sales sync',
        'steps' => [
            'till' => ['title' => 'Till', 'body' => 'Sale saved locally'],
            'queue' => ['title' => 'Queue', 'body' => 'Waits for internet'],
            'cloud' => ['title' => 'VendaPOS cloud', 'body' => 'Synced in batches'],
            'kra' => ['title' => 'KRA eTIMS', 'body' => 'Sent automatically'],
        ],
    ],

    'kds' => [
        'label' => 'Illustration of the kitchen display',
        'station' => 'Grill station',
        'tickets' => [
            'one' => ['table' => 'Table 04', 'items' => '1 × Nyama choma · 1 × Kuku choma'],
            'two' => ['table' => 'Table 09', 'items' => '2 × Fish fillet · 1 × Chips'],
            'three' => ['table' => 'Takeaway 112', 'items' => '1 × Beef samosa (6)'],
        ],
        'bump' => 'Bump',
    ],

    'hardware' => [
        'eyebrow' => 'Hardware',
        'title' => 'Works with the hardware you already have.',
        'lead' => 'No proprietary terminals to buy. VendaPOS runs on standard equipment you can source locally.',
        'items' => [
            'computers' => 'Windows, macOS or Linux PCs and touch screens',
            'printers' => '80 mm ESC/POS thermal printers, USB or network',
            'drawers' => 'Cash drawers, opened through the printer',
            'scanners' => 'Barcode scanners and weighing scales',
            'kds' => 'Kitchen screens running any modern browser',
            'phones' => 'The owner dashboard on any smartphone',
        ],
    ],

    'cta' => [
        'title' => 'See it working on your own counter.',
        'lead' => 'We’ll set up a demo with your products, your branches and your way of working.',
        'button' => 'Book a demo',
    ],
];
