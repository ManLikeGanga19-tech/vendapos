<?php

declare(strict_types=1);

// Messages to tenant owners, by SMS and email.
return [
    'greeting' => 'Hello :name,',
    'contact' => 'Questions? Call :phone.',

    'tenant_suspended' => [
        'sms' => 'VendaPOS: :business is suspended. Tills keep recording sales, but sign-in and new M-Pesa requests are blocked. Unless reactivated, your data will be exported and deleted on :date.',
        'subject' => ':business: your VendaPOS account is suspended',
        'intro' => 'We have suspended the VendaPOS account for :business.',
        'reason' => 'Reason: :reason',
        'still_works' => 'Your tills keep recording and syncing sales, and M-Pesa payments already started still complete.',
        'blocked' => 'Signing in to the owner panel and starting new M-Pesa payments are blocked.',
        'purge' => 'Unless the account is reactivated, a copy of your data will be exported and the data then permanently deleted on :date.',
    ],

    'tenant_reactivated' => [
        'sms' => 'VendaPOS: :business is active again. Sign-in and M-Pesa requests work as before, and nothing will be deleted.',
        'subject' => ':business: your VendaPOS account is active again',
        'body' => 'The suspension of :business is lifted. Sign-in and M-Pesa requests work as before, and nothing will be deleted.',
    ],
];
