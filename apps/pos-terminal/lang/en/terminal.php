<?php

declare(strict_types=1);

return [

    'language' => [
        'en' => 'English',
        'sw' => 'Kiswahili',
    ],

    'pair' => [
        'title' => 'Pair this till',
        'help' => 'In the Owner panel, open Tills and press “Pair a till”. Type the code it shows here.',
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

    'ready' => [
        'title' => 'Till ready',
        'next' => 'Staff sign-in comes in the next update.',
        'items' => 'Menu items',
        'staff' => 'Staff',
        'download_pending' => 'The menu will download as soon as the till is online.',
        'sync_now' => 'Sync now',
    ],

    'status' => [
        'online' => 'Online',
        'offline' => 'Offline: will sync when back online',
        'never' => 'Not synced yet',
        'last_sync' => 'synced :time',
        'suspended' => 'Account suspended: M-Pesa is paused, take cash.',
    ],

];
