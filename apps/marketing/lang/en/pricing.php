<?php

return [
    'meta' => [
        'title' => 'Pricing',
        'description' => 'Simple per-branch VendaPOS plans with M-Pesa, KRA eTIMS and offline selling included. No percentage of your sales.',
    ],

    'hero' => [
        'eyebrow' => 'Pricing',
        'title' => 'Simple plans that grow with your business.',
        'lead' => 'One flat subscription per branch. No percentage of your sales. M-Pesa and card fees are your provider’s standard rates.',
    ],

    'billing' => [
        'label' => 'Billing period',
        'monthly' => 'Monthly',
        'annual' => 'Yearly',
        'saving' => 'Two months free',
    ],

    'included' => [
        'title' => 'Included in every plan',
        'items' => [
            'offline' => 'Offline selling with automatic sync',
            'etims' => 'KRA eTIMS on every receipt',
            'mpesa' => 'M-Pesa STK Push and card payments',
            'isolation' => 'Separate, isolated data for your business',
            'languages' => 'English and Kiswahili, on the till and online',
            'updates' => 'Software updates included',
        ],
    ],

    'matrix' => [
        'title' => 'Compare plans',
        'note' => 'Plan contents are being finalised.',
        'feature' => 'Feature',
        'groups' => [
            'selling' => 'Selling and payments',
            'compliance' => 'Tax and compliance',
            'operations' => 'Operations',
            'management' => 'Management',
            'support' => 'Support',
        ],
        'rows' => [
            'offline' => 'Offline selling',
            'tills' => 'Tills per branch',
            'mpesa' => 'M-Pesa STK Push',
            'cards' => 'Card payments (Pesapal, DPO)',
            'split_bills' => 'Split bills',
            'tabs' => 'Open tabs',
            'etims' => 'KRA eTIMS receipts',
            'vat' => 'VAT categories: 16%, zero-rated, exempt',
            'audit' => 'Audit trail',
            'stock' => 'Stock tracking',
            'batches' => 'Batch and expiry tracking',
            'transfers' => 'Stock transfers between branches',
            'kds' => 'Kitchen display',
            'approvals' => 'Supervisor approval for voids and refunds',
            'dashboard' => 'Owner dashboard',
            'multi_branch' => 'Multi-branch reports',
            'erp' => 'ERP integrations',
            'help' => 'Help centre and email support',
            'priority' => 'Priority phone and WhatsApp support',
            'manager' => 'Dedicated account manager',
        ],
        'values' => [
            'one' => '1',
            'up_to_five' => 'Up to 5',
            'unlimited' => 'Unlimited',
            'included' => 'Included',
            'not_included' => 'Not included',
        ],
    ],

    'addons' => [
        'title' => 'Add-ons',
        'lead' => 'Add what you need to any plan.',
        'items' => [
            'till' => [
                'name' => 'Extra till',
                'body' => 'Another till at the same branch, with its own offline database.',
            ],
            'kds' => [
                'name' => 'Kitchen display screen',
                'body' => 'Live kitchen tickets for another cooking or bar station.',
            ],
            'messaging' => [
                'name' => 'SMS and WhatsApp receipts',
                'body' => 'Send receipts and order-ready messages to customers’ phones.',
            ],
            'onsite' => [
                'name' => 'On-site setup',
                'body' => 'We install and test your hardware and train your staff at your premises.',
            ],
        ],
        'per' => [
            'till_month' => '/ till / month',
            'screen_month' => '/ screen / month',
            'bundle' => '/ 5,000 messages',
            'visit' => '/ visit',
        ],
    ],

    'faq' => [
        'title' => 'Questions, answered',
        'items' => [
            'fees' => [
                'q' => 'Do you take a percentage of my sales?',
                'a' => 'No. You pay a flat subscription. M-Pesa and card transactions are charged at your provider’s standard rates, and those fees go to them, not to us.',
            ],
            'hardware' => [
                'q' => 'What hardware do I need?',
                'a' => 'VendaPOS runs on Windows, macOS or Linux computers and touch screens, and prints to standard 80 mm ESC/POS thermal printers over USB or the network. The cash drawer opens through the printer, and kitchen screens run in any modern browser.',
            ],
            'offline' => [
                'q' => 'What happens when the internet goes down?',
                'a' => 'Your tills keep selling. Each one saves sales to its own local database and syncs them automatically when the connection returns. Cash and card (PDQ) payments work offline; M-Pesa needs a connection.',
            ],
            'etims' => [
                'q' => 'Is KRA eTIMS included?',
                'a' => 'Yes, on every plan. Each sale is sent to KRA eTIMS and the receipt shows its verification QR code. Sales made offline are sent when you reconnect.',
            ],
            'change' => [
                'q' => 'Can I change plans later?',
                'a' => 'Yes. Talk to us whenever your business changes and we’ll move you to the plan that fits.',
            ],
            'data' => [
                'q' => 'Who owns my data?',
                'a' => 'You do. Each business has its own isolated database schema, and we never sell or share your data.',
            ],
        ],
    ],

    'cta' => [
        'title' => 'Not sure which plan fits?',
        'lead' => 'Book a demo and we’ll recommend a plan for your branches and volumes.',
        'button' => 'Book a demo',
    ],
];
