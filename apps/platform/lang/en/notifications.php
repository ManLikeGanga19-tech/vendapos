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

    'mpesa_checks' => [
        'sms' => '{1} VendaPOS: 1 M-Pesa payment at :business needs checking. Open "M-Pesa to check" in the owner panel.|[2,*] VendaPOS: :count M-Pesa payments at :business need checking. Open "M-Pesa to check" in the owner panel.',
        'subject' => '{1} :business: 1 M-Pesa payment to check|[2,*] :business: :count M-Pesa payments to check',
        'intro' => 'Some M-Pesa money at your tills does not add up yet:',
        'kinds' => [
            'unconfirmed_code' => '{1} 1 typed M-Pesa code that Safaricom has not confirmed|[2,*] :count typed M-Pesa codes that Safaricom has not confirmed',
            'amount_mismatch' => '{1} 1 M-Pesa code confirmed for a different amount than the sale|[2,*] :count M-Pesa codes confirmed for a different amount than the sale',
            'code_reused' => '{1} 1 M-Pesa code typed for more than one sale|[2,*] :count M-Pesa codes typed for more than one sale',
            'unclaimed_push' => '{1} 1 M-Pesa payment received with no sale|[2,*] :count M-Pesa payments received with no sale',
            'qr_paid_twice' => '{1} 1 bill paid again by QR after it was already paid|[2,*] :count bills paid again by QR after they were already paid',
            'ticket_paid_twice' => '{1} 1 ticket paid by two sales|[2,*] :count tickets paid by two sales',
            'voided_sale_paid' => '{1} 1 voided sale paid by M-Pesa to refund|[2,*] :count voided sales paid by M-Pesa to refund',
        ],
        'where' => 'Open "M-Pesa to check" in the owner panel to see each one and mark it checked.',
    ],
];
