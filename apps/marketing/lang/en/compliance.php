<?php

return [
    'meta' => [
        'title' => 'Compliance and security',
        'description' => 'How VendaPOS handles KRA eTIMS, VAT, M-Pesa payment verification, card data and the Kenya Data Protection Act.',
    ],

    'hero' => [
        'eyebrow' => 'Compliance and security',
        'title' => 'Built to keep KRA, your customers and your auditors satisfied.',
        'lead' => 'Tax compliance and data protection are part of the VendaPOS core: the same for every business, on every plan.',
    ],

    'status' => [
        'title' => 'KRA eTIMS integrator certification',
        'value' => 'In progress',
        'certified' => 'Certified',
        'note' => 'We will publish our KRA integrator details here once certification is complete.',
    ],

    'etims' => [
        'eyebrow' => 'KRA eTIMS',
        'title' => 'Every sale reaches KRA, even the ones made offline.',
        'steps' => [
            'sale' => ['title' => 'Sale saved first', 'body' => 'The order is stored on the till before anything else, so nothing is lost.'],
            'send' => ['title' => 'Sent to eTIMS', 'body' => 'Submitted to KRA in the background, without slowing the till.'],
            'receipt' => ['title' => 'QR on the receipt', 'body' => 'The printed receipt carries KRA’s verification QR code.'],
            'offline' => ['title' => 'Offline sales caught up', 'body' => 'Sales made offline are sent automatically when the till reconnects.'],
            'retry' => ['title' => 'Nothing slips through', 'body' => 'Failed submissions are retried and flagged for review, never dropped.'],
        ],
    ],

    'vat' => [
        'title' => 'VAT, calculated correctly',
        'items' => [
            'categories' => 'Every item carries a VAT category: standard 16%, zero-rated or exempt.',
            'cents' => 'Amounts are calculated in whole cents, never in floating point, and rounded only when shown.',
            'inclusive' => 'Prices can include VAT, as on Kenyan menus and shelf labels, with VAT shown separately on the receipt.',
        ],
    ],

    'payments' => [
        'eyebrow' => 'Payments',
        'title' => 'Payments you can trust.',
        'items' => [
            'verify' => [
                'title' => 'M-Pesa confirmations are verified',
                'body' => 'Before a sale is marked paid, Safaricom’s confirmation is checked against a secret callback address, Safaricom’s published IP addresses and the original payment request.',
            ],
            'query' => [
                'title' => 'Late confirmations are chased',
                'body' => 'If a confirmation is slow or never arrives, VendaPOS asks Safaricom directly for the payment’s status.',
            ],
            'idempotent' => [
                'title' => 'No double charges',
                'body' => 'Every order and payment has a unique ID, so a retry after a network blip never charges a customer twice.',
            ],
            'cards' => [
                'title' => 'Card data never touches us',
                'body' => 'Card payments run through Pesapal and DPO, so card numbers never reach our systems and PCI-DSS scope stays with them.',
            ],
        ],
    ],

    'data' => [
        'eyebrow' => 'Data protection',
        'title' => 'Your data stays yours.',
        'items' => [
            'isolation' => [
                'title' => 'A separate schema for every business',
                'body' => 'Each business’s data lives in its own isolated database schema, and automated tests check that one business can never read another’s.',
            ],
            'devices' => [
                'title' => 'Every till is identified',
                'body' => 'Each till signs in with its own device credential, which can be revoked if the device is lost or stolen.',
            ],
            'encryption' => [
                'title' => 'Protected on the till',
                'body' => 'Tills use full-disk encryption, and sensitive fields are encrypted in the database.',
            ],
            'dpa' => [
                'title' => 'Kenya Data Protection Act, 2019',
                'body' => 'We collect only what we need, use it only for the purpose it was given for, and delete it when it is no longer needed.',
            ],
            'audit' => [
                'title' => 'A complete audit trail',
                'body' => 'Voids, refunds and cash drawer openings are logged with who did them and when.',
            ],
            'controller' => [
                'title' => 'Who is responsible',
                'body' => 'VendaPOS is built and run by Danamo Tech Limited of Mombasa, Kenya, which is accountable for how platform data is handled.',
            ],
        ],
    ],

    'cta' => [
        'title' => 'Need the details for your auditors?',
        'lead' => 'Book a session and we’ll walk your finance and IT teams through how VendaPOS meets your requirements.',
        'button' => 'Book a session',
        'photo_alt' => 'A pharmacist explaining a prescription to a customer',
    ],
];
