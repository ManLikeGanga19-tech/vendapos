<?php

return [
    'index' => [
        'meta' => [
            'title' => 'Solutions by business type',
            'description' => 'VendaPOS for restaurants, bars, salons, shops, supermarkets, hardware stores, chemists and liquor stores, on one sales, payments and KRA eTIMS core.',
        ],
        'eyebrow' => 'Solutions',
        'title' => 'Built for the way your business sells.',
        'lead' => 'Choose your type of business to see the screens, features and setup made for it. Every one runs on the same reliable core: M-Pesa, KRA eTIMS and offline selling.',
        'other_title' => 'Don’t see your type of business?',
        'other_body' => 'VendaPOS also works for any other shop or service business, with your own products, services and custom fields.',
        'other_cta' => 'Talk to us',
    ],

    'common' => [
        'breadcrumb' => 'Solutions',
        'secondary_cta' => 'See pricing',
        'problems_eyebrow' => 'Built for your business',
        'problem' => 'The problem',
        'fix' => 'With VendaPOS',
        'features_eyebrow' => 'Features',
        'hardware_title' => 'Typical setup',
        'core_title' => 'Plus everything in the VendaPOS core',
        'core' => [
            'mpesa' => ['title' => 'M-Pesa STK Push', 'body' => 'Customers pay from their own phone, and the sale closes when Safaricom confirms.'],
            'etims' => ['title' => 'KRA eTIMS', 'body' => 'Every sale sent to KRA, with the verification QR code on the receipt.'],
            'offline' => ['title' => 'Works offline', 'body' => 'Keep selling when the internet drops; sales sync when it returns.'],
        ],
        'other' => 'Other businesses we serve',
    ],

    'bars' => [
        'meta' => [
            'title' => 'POS for bars, clubs and lounges',
            'description' => 'Bar and club POS with open tabs, bottle service, happy-hour pricing, cover charges, void controls, M-Pesa and KRA eTIMS.',
        ],
        'name' => 'Bars, clubs & lounges',
        'hero' => [
            'eyebrow' => 'For bars, clubs and lounges',
            'title' => 'Keep the drinks flowing and every shilling accounted for.',
            'lead' => 'Open tabs for every table and seat, switch to happy-hour prices on the dot, charge cover at the door and stop unrecorded “staff drinks”, with every sale sent to KRA eTIMS.',
            'primary_cta' => 'Book a bar demo',
            'photo_alt' => 'A bartender preparing drinks behind a well-stocked bar counter',
        ],
        'panel' => [
            'label' => 'Illustration of open tabs on the VendaPOS till',
            'title' => 'Open tabs',
            'badge' => 'Happy hour until 19:00',
            'rows' => [
                'table3' => ['title' => 'Table 3', 'meta' => '4 × Tusker, 2 × soda', 'status' => 'Open'],
                'seat6' => ['title' => 'Bar seat 6 · Wanjiru', 'meta' => 'Bottle service', 'status' => 'Open'],
                'table8' => ['title' => 'Table 8', 'meta' => 'Cover × 2, 3 × cocktails', 'status' => 'Bill requested'],
                'staff' => ['title' => 'Staff drink · Kevin', 'meta' => 'Waiting for a manager', 'status' => 'Needs approval'],
            ],
        ],
        'problems' => [
            'title' => 'Fix the leaks that cost a bar money.',
            'items' => [
                'tabs' => [
                    'title' => 'Tabs that get lost or walk out',
                    'problem' => 'Paper tabs go missing, get mixed up between tables or leave with the customer.',
                    'fix' => 'Every customer and table has a tab on the till that every bartender can see, closed by M-Pesa, card or cash.',
                ],
                'pricing' => [
                    'title' => 'Happy hour and cover charges done by hand',
                    'problem' => 'Staff forget to switch prices back, and door cash is hard to reconcile.',
                    'fix' => 'Happy-hour prices switch on and off by the clock, and cover is rung up at the door like any other sale.',
                ],
                'shrinkage' => [
                    'title' => 'Drinks that never get paid for',
                    'problem' => 'Voids and “staff drinks” hide stock that walked out of the bar.',
                    'fix' => 'Voids and staff drinks need a manager’s approval and are logged with who asked and when.',
                ],
            ],
        ],
        'features' => [
            'title' => 'Everything a busy bar needs.',
            'items' => [
                'tabs' => ['title' => 'Open tabs', 'body' => 'Tabs per customer, seat or table.'],
                'bottles' => ['title' => 'Bottle service', 'body' => 'Sell bottles with mixers and track them to the table.'],
                'happy' => ['title' => 'Happy-hour pricing', 'body' => 'Time-based prices that switch automatically.'],
                'cover' => ['title' => 'Cover charge', 'body' => 'Ring up entry at the door like any other sale.'],
            ],
        ],
        'hardware' => [
            'till' => 'A touch screen till at the bar',
            'printer' => 'A receipt printer with cash drawer',
        ],
        'cta' => [
            'title' => 'See VendaPOS behind your bar.',
            'lead' => 'We’ll set up a demo with your drinks list, your tables and your happy hours.',
            'button' => 'Book a bar demo',
        ],
    ],

    'salons' => [
        'meta' => [
            'title' => 'POS and bookings for salons, spas and kinyozi',
            'description' => 'Salon and barber POS with a booking calendar, timed services, stylist commissions, packages, M-Pesa deposits and KRA eTIMS.',
        ],
        'name' => 'Salons, spas & kinyozi',
        'hero' => [
            'eyebrow' => 'For salons, spas and kinyozi',
            'title' => 'Fill every chair and pay every stylist correctly.',
            'lead' => 'Take bookings with M-Pesa deposits, see who is free at a glance, and work out each stylist’s or barber’s commission automatically.',
            'primary_cta' => 'Book a salon demo',
            'photo_alt' => 'A barber giving a client a haircut',
        ],
        'panel' => [
            'label' => 'Illustration of today’s bookings in VendaPOS',
            'title' => 'Today’s bookings',
            'badge' => 'Nyali branch',
            'rows' => [
                'amina' => ['title' => '09:00 · Braids, Amina', 'meta' => 'Stylist: Zawadi · 3 h', 'status' => 'Deposit paid'],
                'brian' => ['title' => '10:30 · Haircut and beard, Brian', 'meta' => 'Barber: Juma · 45 min', 'status' => 'Checked in'],
                'halima' => ['title' => '13:00 · Manicure, Halima', 'meta' => 'Nail technician: Neema · 1 h', 'status' => 'Booked'],
                'fatma' => ['title' => '15:00 · Bridal package, Fatma', 'meta' => '4 services · 4 h', 'status' => 'Deposit due'],
            ],
        ],
        'problems' => [
            'title' => 'Run the chair, not the paperwork.',
            'items' => [
                'noshows' => [
                    'title' => 'No-shows and double bookings',
                    'problem' => 'Appointments live in a notebook or on WhatsApp, so chairs sit empty or two clients arrive at once.',
                    'fix' => 'A shared booking calendar shows every stylist’s day, and an M-Pesa deposit secures the slot.',
                ],
                'commission' => [
                    'title' => 'Commission arguments at month end',
                    'problem' => 'Working out who did which service, and what they are owed, takes hours and causes disputes.',
                    'fix' => 'Each service is recorded against the stylist or barber who did it, and commission is calculated automatically.',
                ],
                'packages' => [
                    'title' => 'Packages that are hard to track',
                    'problem' => 'Prepaid packages and bridal bookings are tracked on paper and easily lost.',
                    'fix' => 'Sell packages on the till and see which services each client has used.',
                ],
            ],
        ],
        'features' => [
            'title' => 'Built for chairs, not checkouts.',
            'items' => [
                'calendar' => ['title' => 'Booking calendar', 'body' => 'Every stylist’s day on one screen.'],
                'timed' => ['title' => 'Timed services', 'body' => 'Each service blocks the right amount of time.'],
                'staff' => ['title' => 'Stylist per service', 'body' => 'The right stylist or barber for each service.'],
                'commission' => ['title' => 'Commission', 'body' => 'Worked out per staff member, per service.'],
            ],
        ],
        'hardware' => [
            'tablet' => 'A tablet or touch screen at reception',
            'phone' => 'The owner dashboard on your phone',
        ],
        'cta' => [
            'title' => 'See VendaPOS run your salon.',
            'lead' => 'We’ll set up a demo with your services, your stylists and your opening hours.',
            'button' => 'Book a salon demo',
        ],
    ],

    'shops' => [
        'meta' => [
            'title' => 'POS for shops, dukas and kiosks',
            'description' => 'Simple shop POS with barcode scanning, quick keys, a customer credit book (deni), M-Pesa and KRA eTIMS, even offline.',
        ],
        'name' => 'Shops, dukas & kiosks',
        'hero' => [
            'eyebrow' => 'For shops, dukas and kiosks',
            'title' => 'Sell faster, and know exactly who owes you.',
            'lead' => 'Scan or tap to sell in seconds, keep your deni book on the till instead of in an exercise book, and send every sale to KRA eTIMS, even when the internet is down.',
            'primary_cta' => 'Book a shop demo',
            'photo_alt' => 'A shopkeeper at the counter of her shop in Dar es Salaam',
        ],
        'panel' => [
            'label' => 'Illustration of the customer credit book in VendaPOS',
            'title' => 'Deni book',
            'badge' => '3 customers',
            'rows' => [
                'otieno' => ['title' => 'Mama Otieno', 'meta' => 'Last paid 2 days ago', 'status' => 'Within limit'],
                'hassan' => ['title' => 'Hassan Ali', 'meta' => 'Limit KES 5,000', 'status' => 'Near limit'],
                'grace' => ['title' => 'Grace W.', 'meta' => 'Paid by M-Pesa today', 'status' => 'Cleared'],
            ],
        ],
        'problems' => [
            'title' => 'Small shop, big headaches, solved.',
            'items' => [
                'queue' => [
                    'title' => 'Queues at the counter',
                    'problem' => 'Looking up prices and adding totals by hand slows every sale.',
                    'fix' => 'Scan the barcode or tap a quick key, and the price, total and change are on screen instantly.',
                ],
                'deni' => [
                    'title' => 'Deni that never gets paid',
                    'problem' => 'Credit is written in an exercise book that gets lost, smudged or disputed.',
                    'fix' => 'Every credit sale is recorded against the customer with a limit, and repayments are recorded against their balance.',
                ],
                'stock' => [
                    'title' => 'Not knowing what is selling',
                    'problem' => 'You only find out an item has run out when a customer asks for it.',
                    'fix' => 'See what sells, what is running low and today’s takings, from your phone.',
                ],
            ],
        ],
        'features' => [
            'title' => 'Simple enough for a kiosk, ready to grow.',
            'items' => [
                'scan' => ['title' => 'Barcode scanning', 'body' => 'Scan to sell, or search by name.'],
                'keys' => ['title' => 'Quick keys', 'body' => 'Your best sellers one tap away.'],
                'deni' => ['title' => 'Customer credit book', 'body' => 'Credit limits and balances per customer.'],
                'reports' => ['title' => 'Daily sales', 'body' => 'Today’s sales on your phone.'],
            ],
        ],
        'hardware' => [
            'scanner' => 'A barcode scanner',
            'printer' => 'A receipt printer',
        ],
        'cta' => [
            'title' => 'See VendaPOS on your counter.',
            'lead' => 'We’ll set up a demo with your products and prices.',
            'button' => 'Book a shop demo',
        ],
    ],

    'supermarkets' => [
        'meta' => [
            'title' => 'POS for supermarkets and minimarts',
            'description' => 'Multi-lane supermarket POS with barcode and weighing-scale codes, promotions and bundles, shift cash-ups, loyalty, M-Pesa and KRA eTIMS.',
        ],
        'name' => 'Supermarkets & minimarts',
        'hero' => [
            'eyebrow' => 'For supermarkets and minimarts',
            'title' => 'Every lane fast, every shift balanced.',
            'lead' => 'Run several checkout lanes with barcode and scale codes, promotions that apply themselves and shift cash-ups that balance, and keep selling through internet outages.',
            'primary_cta' => 'Book a supermarket demo',
            'photo_alt' => 'A shopping trolley in a supermarket aisle',
        ],
        'panel' => [
            'label' => 'Illustration of checkout lanes in VendaPOS',
            'title' => 'Lanes, Mombasa CBD',
            'badge' => '3 of 4 lanes open',
            'rows' => [
                'lane1' => ['title' => 'Lane 1 · Aisha', 'meta' => '42 sales this shift', 'status' => 'Open'],
                'lane2' => ['title' => 'Lane 2 · Omar', 'meta' => '37 sales this shift', 'status' => 'Open'],
                'lane3' => ['title' => 'Lane 3 · Mwanaisha', 'meta' => 'Weighing tomatoes, 1.2 kg', 'status' => 'Open'],
                'lane4' => ['title' => 'Lane 4', 'meta' => 'Shift closed, cash-up balanced', 'status' => 'Closed'],
            ],
        ],
        'problems' => [
            'title' => 'Keep queues short and margins honest.',
            'items' => [
                'queues' => [
                    'title' => 'Long queues at peak times',
                    'problem' => 'Slow scanning and manual weighing hold up every lane.',
                    'fix' => 'Barcode and weighing-scale codes ring up packaged and loose items in one step.',
                ],
                'promos' => [
                    'title' => 'Promotions applied by hand',
                    'problem' => 'Cashiers forget offers, or apply them when they shouldn’t.',
                    'fix' => 'Promotions and bundles apply automatically at the till, exactly as you set them up.',
                ],
                'cashups' => [
                    'title' => 'Shifts that don’t balance',
                    'problem' => 'Counting drawers at shift change is slow and differences are hard to trace.',
                    'fix' => 'Each cashier’s shift is cashed up on the till, with any variance recorded against their name.',
                ],
            ],
        ],
        'features' => [
            'title' => 'Built for many lanes and many shifts.',
            'items' => [
                'scale' => ['title' => 'Barcode and scale codes', 'body' => 'Weighed items priced from scale barcodes.'],
                'promos' => ['title' => 'Promotions and bundles', 'body' => 'Offers that apply automatically.'],
                'lanes' => ['title' => 'Cashier lanes', 'body' => 'Every lane and cashier, live.'],
                'loyalty' => ['title' => 'Loyalty', 'body' => 'Reward your regular customers.'],
            ],
        ],
        'hardware' => [
            'scanner' => 'A barcode scanner at every lane',
            'scale' => 'Weighing scales',
            'drawer' => 'Cash drawers',
            'display' => 'Customer-facing displays',
        ],
        'cta' => [
            'title' => 'See VendaPOS run your lanes.',
            'lead' => 'We’ll set up a demo with your products, lanes and promotions.',
            'button' => 'Book a supermarket demo',
        ],
    ],

    'hardware' => [
        'meta' => [
            'title' => 'POS for hardware stores',
            'description' => 'Hardware store POS with units of measure, quotations to invoices, delivery notes, contractor pricing, credit accounts, M-Pesa and KRA eTIMS.',
        ],
        'name' => 'Hardware stores',
        'hero' => [
            'eyebrow' => 'For hardware stores',
            'title' => 'From quotation to delivery, in metres, kilos and bags.',
            'lead' => 'Sell by the piece, metre, kilogram or bag, turn quotations into invoices in one step, and keep contractors’ prices and credit accounts in order.',
            'primary_cta' => 'Book a hardware demo',
            'photo_alt' => 'Shelves stacked high with stock in a hardware store',
        ],
        'panel' => [
            'label' => 'Illustration of a quotation in VendaPOS',
            'title' => 'Quotation Q-1042 · Baraka Builders',
            'badge' => 'Contractor prices',
            'rows' => [
                'cement' => ['title' => 'Cement, 50 kg', 'meta' => '40 bags'],
                'steel' => ['title' => 'Y12 steel bar', 'meta' => '120 m'],
                'nails' => ['title' => 'Roofing nails', 'meta' => '25 kg'],
            ],
        ],
        'problems' => [
            'title' => 'Sell the way the trade buys.',
            'items' => [
                'units' => [
                    'title' => 'Selling in the wrong units',
                    'problem' => 'Stock is bought in bags and sold in kilos, or bought in lengths and sold in metres, and the numbers never match.',
                    'fix' => 'Units of measure and conversions keep stock right whether you sell a bag, a kilo, a length or a metre.',
                ],
                'quotes' => [
                    'title' => 'Quotes retyped as invoices',
                    'problem' => 'Quotations are written by hand and retyped when the customer comes back, with mistakes.',
                    'fix' => 'Turn a saved quotation into an invoice in one step, and print a delivery note for the driver.',
                ],
                'credit' => [
                    'title' => 'Contractor credit out of control',
                    'problem' => 'Special prices and credit for regular contractors are tracked in someone’s head.',
                    'fix' => 'Contractor price lists and credit accounts with limits apply as soon as you select the customer.',
                ],
            ],
        ],
        'features' => [
            'title' => 'Built for the trade counter.',
            'items' => [
                'units' => ['title' => 'Units of measure', 'body' => 'Piece, metre, kg, bag and your own conversions.'],
                'quotes' => ['title' => 'Quotation to invoice', 'body' => 'Convert quotations to invoices without retyping.'],
                'delivery' => ['title' => 'Delivery notes', 'body' => 'A delivery note for every dispatch.'],
                'contractors' => ['title' => 'Contractor pricing and credit', 'body' => 'Price lists and credit limits per contractor.'],
            ],
        ],
        'hardware' => [
            'scanner' => 'A barcode scanner',
            'a4' => 'An A4 printer for quotations and invoices',
        ],
        'cta' => [
            'title' => 'See VendaPOS behind your counter.',
            'lead' => 'We’ll set up a demo with your products, units and contractor prices.',
            'button' => 'Book a hardware demo',
        ],
    ],

    'pharmacy' => [
        'meta' => [
            'title' => 'POS for chemists and pharmacies',
            'description' => 'Pharmacy POS with batch and expiry tracking, prescription records, a controlled-items register, pharmacist sign-off, insurance billing, M-Pesa and KRA eTIMS.',
        ],
        'name' => 'Chemists & pharmacies',
        'hero' => [
            'eyebrow' => 'For chemists and pharmacies',
            'title' => 'Sell the oldest stock first and catch expiries before they cost you.',
            'lead' => 'Batch and expiry tracking, prescription records, a controlled-items register and pharmacist sign-off, built into the till instead of kept in separate books.',
            'primary_cta' => 'Book a pharmacy demo',
            'photo_alt' => 'A pharmacist explaining a prescription to a customer',
        ],
        'panel' => [
            'label' => 'Illustration of batch tracking in VendaPOS',
            'title' => 'Amoxicillin 500 mg · batches',
            'badge' => 'First expiry, first out',
            'rows' => [
                'a23' => ['title' => 'Batch A23', 'meta' => 'Expires Dec 2026 · 40 packs', 'status' => 'Sell first'],
                'b07' => ['title' => 'Batch B07', 'meta' => 'Expires Jun 2027 · 120 packs', 'status' => 'In stock'],
                'a11' => ['title' => 'Batch A11', 'meta' => 'Expires Nov 2026 · 3 packs', 'status' => 'Expiring soon'],
            ],
        ],
        'problems' => [
            'title' => 'Run a safer, tidier pharmacy.',
            'items' => [
                'expiry' => [
                    'title' => 'Stock expiring on the shelf',
                    'problem' => 'Older batches sit behind newer ones until they expire and are written off.',
                    'fix' => 'Every sale picks the batch that expires first, and expiring stock is flagged in advance.',
                ],
                'records' => [
                    'title' => 'Records kept in separate books',
                    'problem' => 'Prescriptions and controlled items are recorded by hand, apart from the sale.',
                    'fix' => 'Prescription records and the controlled-items register are captured on the till as part of the sale.',
                ],
                'signoff' => [
                    'title' => 'Sales without a pharmacist’s check',
                    'problem' => 'Medicines that need a pharmacist can be sold without one noticing.',
                    'fix' => 'Items that need sign-off wait for a pharmacist to approve them before the sale completes.',
                ],
            ],
        ],
        'features' => [
            'title' => 'Built for regulated retail.',
            'items' => [
                'batches' => ['title' => 'Batch and expiry', 'body' => 'First-expiry-first-out on every sale.'],
                'prescriptions' => ['title' => 'Prescription records', 'body' => 'Prescriptions linked to the sale.'],
                'register' => ['title' => 'Controlled-items register', 'body' => 'Kept up to date as you sell.'],
                'insurance' => ['title' => 'Insurance billing', 'body' => 'Bill insured customers through their cover.'],
            ],
        ],
        'hardware' => [
            'scanner' => 'A barcode scanner',
            'printer' => 'A receipt printer',
        ],
        'cta' => [
            'title' => 'See VendaPOS in your pharmacy.',
            'lead' => 'We’ll set up a demo with your products and batches.',
            'button' => 'Book a pharmacy demo',
        ],
    ],

    'liquor' => [
        'meta' => [
            'title' => 'POS for liquor stores',
            'description' => 'Liquor store POS with age-check prompts, licensed selling hours, case and bulk pricing, M-Pesa and KRA eTIMS.',
        ],
        'name' => 'Liquor stores',
        'hero' => [
            'eyebrow' => 'For liquor stores and wines & spirits shops',
            'title' => 'Sell within your licence, every time.',
            'lead' => 'Age-check prompts at the till, alcohol sales locked outside your licensed hours, and case and bulk prices that apply themselves.',
            'primary_cta' => 'Book a liquor store demo',
            'photo_alt' => 'Shelves of bottled drinks in a liquor store',
        ],
        'panel' => [
            'label' => 'Illustration of licence checks on the VendaPOS till',
            'title' => 'Till checks',
            'badge' => 'Within licensed hours',
            'rows' => [
                'age' => ['title' => 'Age check', 'meta' => 'Confirm the customer is 18 or over', 'status' => 'Prompted'],
                'hours' => ['title' => 'Licensed hours', 'meta' => 'Sales allowed until 23:00', 'status' => 'Open'],
                'case' => ['title' => 'Tusker lager 500 ml × 24', 'meta' => 'Case price applied', 'status' => 'Case'],
                'single' => ['title' => 'Tusker lager 500 ml', 'meta' => 'Single bottle'],
            ],
        ],
        'problems' => [
            'title' => 'Stay on the right side of your licence.',
            'items' => [
                'age' => [
                    'title' => 'Underage sales',
                    'problem' => 'A busy cashier forgets to check ID.',
                    'fix' => 'The till asks for an age check on every alcohol sale before it can complete.',
                ],
                'hours' => [
                    'title' => 'Sales outside licensed hours',
                    'problem' => 'One late sale can put a licence at risk.',
                    'fix' => 'Set your licensed hours and the till stops alcohol sales outside them.',
                ],
                'bulk' => [
                    'title' => 'Case and bulk pricing done by hand',
                    'problem' => 'Cashiers work out case discounts on a calculator and get them wrong.',
                    'fix' => 'Case and bulk prices apply automatically when the quantity is reached.',
                ],
            ],
        ],
        'features' => [
            'title' => 'Built for licensed retail.',
            'items' => [
                'age' => ['title' => 'Age-check prompt', 'body' => 'Asked on every alcohol sale.'],
                'hours' => ['title' => 'Sale-hours lock', 'body' => 'Selling hours set per licence.'],
                'cases' => ['title' => 'Case and bulk pricing', 'body' => 'Prices by bottle, case or crate.'],
                'stock' => ['title' => 'Stock by bottle and case', 'body' => 'Receive by the case, sell by the bottle.'],
            ],
        ],
        'hardware' => [
            'scanner' => 'A barcode scanner',
            'printer' => 'A receipt printer',
        ],
        'cta' => [
            'title' => 'See VendaPOS in your shop.',
            'lead' => 'We’ll set up a demo with your products, cases and licensed hours.',
            'button' => 'Book a liquor store demo',
        ],
    ],
];
