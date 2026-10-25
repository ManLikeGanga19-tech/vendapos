<?php

declare(strict_types=1);

return [

    'language' => [
        'en' => 'English',
        'sw' => 'Kiswahili',
    ],

    'pair' => [
        'title' => 'Pair this till',
        'help' => 'On a computer or phone, open the Owner panel, go to Tills and press “Pair a till”. Type the 8-character code it shows into the boxes below. The code works once, for 15 minutes.',
        'not_used' => 'Codes never use 0, 1, I, L, O or U, so they can’t be misread. Those keys are left out on purpose.',
        'code' => 'Pairing code',
        'submit' => 'Pair till',
        'working' => 'Pairing and downloading the menu…',
        'backspace' => 'Delete',
        'clear' => 'Clear',
        'code_invalid' => 'A code has 8 letters and numbers, like ABCD-2345.',
        'code_refused' => 'This code can’t be used. Ask for a new one.',
        'too_many_tries' => 'Too many tries. Wait a minute, then try again.',
        'offline' => 'No internet connection. Pairing needs the internet; selling later does not.',
        'not_configured' => 'This till was built without a server address. Contact VendaPOS support.',
        'keyring' => 'This till can’t store its key safely. Contact VendaPOS support.',
        'removed' => 'This till was removed from the business. Ask a manager to pair it again.',
    ],

    'roles' => [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'cashier' => 'Cashier',
        'waiter' => 'Waiter',
    ],

    'sign_in' => [
        'title' => 'Who’s working?',
        'choose' => 'Tap your name.',
        'enter_pin' => 'Enter your PIN',
        'back' => 'Back',
        'wrong' => 'Wrong PIN. :count try left.|Wrong PIN. :count tries left.',
        'locked' => 'Too many wrong PINs. Try again at :time.',
        'no_staff' => 'No one can sign in yet',
        'no_staff_help' => 'In the Owner panel, open Staff and give people a PIN. The till picks them up within a minute.',
        'download_pending' => 'The menu and staff will download as soon as the till is online.',
        'sync_now' => 'Sync now',
    ],

    'home' => [
        'signed_in_as' => 'Signed in as :name',
        'next' => 'Selling comes in the next update.',
        'items' => 'Menu items',
        'staff' => 'Staff',
        'switch' => 'Switch user',
        'sign_out' => 'Sign out',
        'change_pin' => 'Change my PIN',
    ],

    'pin_change' => [
        'title' => 'Change my PIN',
        'online_only' => 'Needs internet',
        'steps' => [
            'current' => 'Enter your current PIN',
            'new' => 'Choose a new :length-digit PIN',
            'confirm' => 'Type the new PIN again',
        ],
        'mismatch' => 'The two new PINs were different. Choose the new PIN again.',
        'done' => 'Your PIN is changed. Use the new one next time you sign in.',
        'reasons' => [
            'wrong_pin' => 'That is not your current PIN.',
            'locked' => 'Too many wrong PINs. Wait a few minutes and try again.',
            'wrong_length' => 'The new PIN has the wrong number of digits.',
            'too_easy' => 'That PIN is too easy to guess. Avoid repeats like 7777 and runs like 3456.',
            'same_pin' => 'The new PIN is the same as the current one.',
            'too_many_tries' => 'Too many tries. Wait a few minutes and try again.',
            'offline' => 'No internet connection. Changing a PIN needs the internet.',
            'not_on_server' => 'The server could not find you. Ask a manager to check the Owner panel.',
        ],
    ],

    'status' => [
        'online' => 'Online',
        'offline' => 'Offline: will sync when back online',
        'never' => 'Not synced yet',
        'last_sync' => 'synced :time',
        'suspended' => 'Account suspended: M-Pesa is paused, take cash.',
    ],

];
