<?php

return [
    'meta' => [
        'title' => 'POS for restaurants and cafés',
        'description' => 'Restaurant POS with floor plans, courses, modifiers, kitchen display, split bills, tips, M-Pesa and KRA eTIMS — and it keeps working offline.',
    ],

    'breadcrumb' => [
        'solutions' => 'Solutions',
        'current' => 'Restaurants & cafés',
    ],

    'hero' => [
        'eyebrow' => 'For restaurants, cafés and hotel kitchens',
        'title' => 'Turn tables faster, without order mix-ups.',
        'lead' => 'From a beachfront café in Diani to a busy dining room in Nyali: table service, kitchen screens and M-Pesa at the till, with every bill sent to KRA eTIMS — even when the internet drops mid-service.',
        'primary_cta' => 'Book a restaurant demo',
        'secondary_cta' => 'See pricing',
        'photo_alt' => 'A chef plating a dish in a Nairobi hotel kitchen',
    ],

    'floor' => [
        'label' => 'Illustration of the VendaPOS floor plan during service',
        'title' => 'Terrace and main hall',
        'occupied' => ':count of :total tables occupied',
        'states' => [
            'seated' => 'Seated',
            'mains' => 'Mains fired',
            'bill' => 'Bill requested',
            'free' => 'Free',
        ],
        'guests' => ':count guests',
    ],

    'problems' => [
        'eyebrow' => 'Built for the rush',
        'title' => 'Fix the three bottlenecks that slow a dining room down.',
        'problem' => 'The problem',
        'fix' => 'With VendaPOS',
        'items' => [
            'kitchen' => [
                'title' => 'Orders lost between the floor and the kitchen',
                'problem' => 'Handwritten chits go missing, modifiers get misread and food goes out cold.',
                'fix' => 'Orders go straight from the till to the right kitchen or bar screen, with modifiers spelled out and a timer on every ticket.',
            ],
            'payments' => [
                'title' => 'Slow bills and M-Pesa you have to check by hand',
                'problem' => 'Waiters wait for confirmation messages and managers chase missing payments at closing.',
                'fix' => 'Send an STK push from the till. The bill closes itself when Safaricom confirms, and splits by item or equally in a few taps.',
            ],
            'closing' => [
                'title' => 'Closing the day takes too long',
                'problem' => 'Counting cash, tallying tips and filing receipts for KRA eats into the night.',
                'fix' => 'Shift cash-ups, tips recorded per server and eTIMS receipts sent as you sell, so the day closes when service does.',
            ],
        ],
    ],

    'features' => [
        'eyebrow' => 'Everything for table service',
        'title' => 'From the first order to the last receipt.',
        'items' => [
            'floor' => ['title' => 'Floor plans and tables', 'body' => 'Lay out your terrace, hall and VIP areas and see every table’s status at a glance.'],
            'courses' => ['title' => 'Courses', 'body' => 'Send starters now and fire mains when the table is ready.'],
            'modifiers' => ['title' => 'Modifiers', 'body' => '“Medium-well, no onions, extra kachumbari” reaches the cook exactly as ordered.'],
            'kds' => ['title' => 'Kitchen display', 'body' => 'Tickets routed to grill, kitchen and bar screens, with timers and bump.'],
            'split' => ['title' => 'Split bills', 'body' => 'Split by item or equally, and take each part by M-Pesa, card or cash.'],
            'tips' => ['title' => 'Tips', 'body' => 'Tips are recorded against the server who earned them.'],
            'etims' => ['title' => 'KRA eTIMS', 'body' => 'Every bill is sent to KRA eTIMS and printed with its QR code.'],
            'offline' => ['title' => 'Works offline', 'body' => 'Keep taking orders and printing bills when the internet drops.'],
        ],
    ],

    'kitchen' => [
        'eyebrow' => 'Kitchen display',
        'title' => 'The kitchen sees the order the moment it’s placed.',
        'body' => 'No more chits. Each station sees only its own tickets, oldest first, with a timer that turns amber and then red as it waits.',
    ],

    'hardware' => [
        'title' => 'Typical restaurant setup',
        'items' => [
            'till' => 'A touch screen till at the counter',
            'printer' => 'A kitchen printer or kitchen screen per station',
            'receipts' => 'A receipt printer with cash drawer',
        ],
    ],

    'cta' => [
        'title' => 'See VendaPOS run your dining room.',
        'lead' => 'We’ll set up a demo with your menu, your floor plan and your stations.',
        'button' => 'Book a restaurant demo',
        'photo_alt' => 'A bartender preparing drinks behind a well-stocked bar counter',
    ],
];
