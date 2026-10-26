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

    'tenant_purge_reminder' => [
        'sms' => '{1} VendaPOS reminder: :business is still suspended. Unless reactivated, its data will be exported and deleted tomorrow, :date.|[2,*] VendaPOS reminder: :business is still suspended. Unless reactivated, its data will be exported and deleted in :days days, on :date.',
        'subject' => '{1} :business: your data will be deleted tomorrow|[2,*] :business: your data will be deleted in :days days',
        'body' => '{1} :business is still suspended. Unless the account is reactivated, a copy of its data will be exported and the data then permanently deleted tomorrow, :date.|[2,*] :business is still suspended. Unless the account is reactivated, a copy of its data will be exported and the data then permanently deleted in :days days, on :date.',
    ],

    'tenant_purged' => [
        'sms' => 'VendaPOS: as notified, the data of :business has been deleted. An encrypted copy is kept until :date.',
        'subject' => ':business: your VendaPOS data has been deleted',
        'body' => 'As we notified you, the data of :business has been permanently deleted from VendaPOS. An encrypted copy is kept until :date; contact us if you need it.',
    ],

    'payment_account_changed' => [
        'sms' => 'VendaPOS: :name changed payment account ":account" of :business (:change) on :date at :time. If this was not you, sign in and check it now.',
        'subject' => ':business: payment account ":account" was changed',
        'body' => ':name changed the payment account ":account" (:change) on :date at :time.',
        'not_you' => 'If you did not expect this, sign in to VendaPOS and check your payment accounts now: customers’ payments may be going to another paybill or till.',
        'actions' => [
            'added' => 'added',
            'updated' => 'keys or details changed',
            'branches_changed' => 'branches changed',
        ],
    ],
];
