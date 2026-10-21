<?php

return [
    'meta' => [
        'title' => 'Book a demo',
        'description' => 'Book a VendaPOS walkthrough tailored to your restaurant, bar, salon, shop, supermarket, hardware store or chemist.',
    ],

    'intro' => [
        'eyebrow' => 'Live demo',
        'title' => 'See VendaPOS on your counter.',
        'lead' => 'A walkthrough tailored to your business, in English or Kiswahili. No obligation.',
        'benefits_title' => 'In your demo you will:',
        'benefits' => [
            'offline' => 'Watch a till keep selling through a simulated internet outage, then sync.',
            'mpesa' => 'Take a live M-Pesa STK payment and see it confirm on the till.',
            'etims' => 'See a sale reach KRA eTIMS and print with its verification QR code.',
            'branches' => 'Explore stock, staff and sales across branches from the owner dashboard.',
        ],
        'photo_alt' => 'A shopkeeper at the counter of her shop in Dar es Salaam',
    ],

    'form' => [
        'title' => 'Request your demo',
        'lead' => 'Tell us a little about your business and we’ll get back to you to arrange a time.',
        'errors_title' => 'Please check the highlighted fields.',
        'optional' => 'Optional',
        'choose' => 'Choose one',
        'any_time' => 'Any time',
        'submit' => 'Request demo',
        'consent' => 'I agree that VendaPOS may store these details and contact me about this demo, as described below.',
        'honeypot' => 'Leave this field empty',
    ],

    'hints' => [
        'phone' => 'We’ll confirm by phone or WhatsApp. Kenyan numbers can start with 07 or 01.',
        'message' => 'For example: your current system, number of tills or go-live date.',
    ],

    'fields' => [
        'request_id' => 'form',
        'form_token' => 'form',
        'website' => 'website',
        'name' => 'Full name',
        'email' => 'Work email',
        'phone' => 'Phone number',
        'business_name' => 'Business name',
        'business_type' => 'Type of business',
        'outlets' => 'Number of outlets',
        'contact_window' => 'Best time to call',
        'message' => 'Anything we should know?',
        'consent' => 'Consent',
    ],

    'business_types' => [
        'restaurant' => 'Restaurant, café or hotel kitchen',
        'bar' => 'Bar, club or lounge',
        'salon' => 'Salon, spa or kinyozi',
        'shop' => 'Shop, duka or kiosk',
        'supermarket' => 'Supermarket or minimart',
        'hardware' => 'Hardware store',
        'pharmacy' => 'Chemist or pharmacy',
        'liquor' => 'Liquor store',
        'general' => 'Other business',
    ],

    'outlets' => [
        '1' => '1 outlet',
        '2-5' => '2–5 outlets',
        '6-15' => '6–15 outlets',
        '16-50' => '16–50 outlets',
        '50+' => 'More than 50 outlets',
    ],

    'contact_windows' => [
        'morning' => 'Morning (9:00–12:00 EAT)',
        'afternoon' => 'Afternoon (14:00–17:00 EAT)',
    ],

    'privacy' => [
        'title' => 'How we use your details',
        'controller' => 'Your details are held by DanamoTech Ltd of Mombasa, Kenya, which builds and runs VendaPOS.',
        'use' => 'We use these details only to arrange and follow up on your demo. We never sell or share them.',
        'retention' => 'We keep them for :months months, then delete them.',
        'rights' => 'To see, correct or delete your details, email :email.',
        'law' => 'We handle your data in line with the Kenya Data Protection Act, 2019.',
    ],

    'validation' => [
        'required' => ':Attribute is required.',
        'email' => 'Enter a valid email address, like name@business.co.ke.',
        'max' => ':Attribute must be :max characters or fewer.',
        'choice' => ':Attribute: please choose one of the options.',
        'phone' => 'Enter a valid phone number, like 0712 345 678 or +256 772 123 456.',
        'consent' => 'Please agree so we can contact you about your demo.',
        'expired' => 'This form has expired. Please reload the page and try again.',
        'unknown' => 'The form contained unexpected fields. Please reload the page and try again.',
    ],

    'thanks' => [
        'title' => 'Thank you — your request is in.',
        'body' => 'We’ll contact you shortly to arrange a time that suits you. We’ve also sent a confirmation to your email.',
        'back' => 'Back to home',
    ],

    'mail' => [
        'sales' => [
            'subject' => 'New demo request: :business',
            'intro' => 'A new demo request has come in from the website.',
            'language' => 'Language',
            'reference' => 'Reference',
        ],
        'visitor' => [
            'subject' => 'We’ve received your VendaPOS demo request',
            'greeting' => 'Hello :name,',
            'body' => 'Thank you for your interest in VendaPOS. We’ve received your demo request for :business.',
            'next' => 'Our team will contact you shortly to arrange a time that suits you.',
            'signoff' => 'The VendaPOS team',
        ],
    ],
];
