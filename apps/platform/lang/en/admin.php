<?php

declare(strict_types=1);

return [
    'tenant' => [
        'overview' => 'Tenant',
        'branches' => 'Branches',
        'devices' => 'Devices',
        'etims' => 'eTIMS',
        'negative_stock' => 'Negative stock',
        'void_rates' => 'High void rates',
        'exports' => 'Data exports',
    ],

    'exports' => [
        'taken' => 'Taken',
        'reason' => 'Why',
        'reasons' => [
            'manual' => 'Requested',
            'purge' => 'Before deletion',
        ],
        'status' => 'Status',
        'statuses' => [
            'pending' => 'In progress',
            'verified' => 'Verified',
            'failed' => 'Failed',
        ],
        'size' => 'Size',
        'rows' => 'Rows',
        'kept_until' => 'Kept until',
        'export_now' => 'Export now',
        'export_confirm' => 'Takes a full, encrypted copy of this tenant’s data. It runs in the background; this list updates when it is done.',
        'queued' => 'Export started',
        'download' => 'Download',
    ],

    'tenant_form' => [
        'name' => 'Business name',
        'owner' => 'Owner',
        'owner_help' => 'Who we contact about the account, including suspension notices.',
        'owner_name' => 'Owner name',
        'owner_phone' => 'Owner phone',
        'owner_email' => 'Owner email',
        'owner_phone_invalid' => 'Enter a valid phone number, for example 0712 345 678.',
        'locale' => 'Owner’s language',
        'locale_help' => 'SMS and emails to the owner are written in this language.',
        'locales' => [
            'en' => 'English',
            'sw' => 'Kiswahili',
        ],
    ],

    'suspension' => [
        'status' => 'Status',
        'statuses' => [
            'active' => 'Active',
            'suspended' => 'Suspended',
            'purged' => 'Purged',
        ],
        'suspend' => 'Suspend',
        'suspend_heading' => 'Suspend :name?',
        'suspend_description' => 'Owner and manager sign-in, new M-Pesa requests and new tills are blocked at once; existing tills keep syncing sales. The owner is told by SMS and email. Unless reactivated, the data is exported and permanently deleted after :days days.',
        'reason' => 'Reason',
        'reason_help' => 'Shown to the owner in the email and kept in the audit trail.',
        'suspended' => 'Tenant suspended; the owner is being notified',
        'reactivate' => 'Reactivate',
        'reactivate_description' => 'Everything works again at once, the deletion is cancelled and the owner is notified.',
        'reactivated' => 'Tenant reactivated; the owner is being notified',
        'conflict' => 'This tenant’s status was changed by someone else; showing it as it is now.',
        'subheading' => 'Suspended since :since. Data will be exported and deleted on :purge. Reason: :reason',
    ],

    'owner_login' => [
        'action' => 'Owner login link',
        'confirm' => 'This creates a one-time link for :name to choose a username and password. Any earlier link stops working.',
        'created' => 'Link for :name, valid for :hours hours',
        'copy_hint' => 'Copy it now and send it to the owner. It cannot be shown again.',
        'no_domain' => 'This tenant has no domain yet, so there is nowhere to sign in.',
    ],

    'branches' => [
        'name' => 'Branch',
        'business_type' => 'Business type',
        'etims_branch_id' => 'KRA branch ID',
        'etims_enabled' => 'eTIMS on',
        'set_etims_branch_id' => 'Set KRA branch ID',
        'etims_branch_id_help' => 'The branch ID (bhfId) KRA issued for this branch. Leave empty to clear it.',
        'switch_etims_on' => 'Switch eTIMS on',
        'switch_etims_off' => 'Switch eTIMS off',
        'switch_etims_on_confirm' => 'From now on this branch’s sales will be sent to KRA eTIMS.',
        'switch_etims_off_confirm' => 'New sales from this branch will no longer be sent to KRA. Documents already queued are still sent.',
        'saved' => 'Branch updated.',
    ],

    'devices' => [
        'name' => 'Device',
        'branch' => 'Branch',
        'last_seen' => 'Last seen',
        'never' => 'Never',
        'token_expires' => 'Token expires',
        'status' => 'Status',
        'active' => 'Active',
        'revoked' => 'Revoked',
        'revoke' => 'Revoke',
        'revoke_confirm' => 'The device is signed out at once and cannot sync until it is enrolled again.',
        'revoked_notice' => 'Device revoked.',
    ],

    'etims' => [
        'order' => 'Order',
        'branch' => 'Branch',
        'document' => 'Document',
        'sale' => 'Sale',
        'credit_note' => 'Credit note',
        'status' => 'Status',
        'pending' => 'Pending',
        'failed' => 'Failed',
        'attempts' => 'Attempts',
        'next_attempt' => 'Next attempt',
        'last_error' => 'Last error',
        'queued' => 'Queued',
        'retry_now' => 'Retry now',
        'retry_confirm' => 'Send this document to KRA again now. A failed document gets one more attempt.',
        'retry_queued' => 'Retry queued.',
        'empty' => 'Nothing waiting: every eTIMS document has been sent or is not required.',
    ],

    'void_rates' => [
        'day' => 'Day',
        'branch' => 'Branch',
        'staff' => 'Staff',
        'voided' => 'Voided / sales',
        'status' => 'Owner',
        'open' => 'Not checked yet',
        'resolved' => 'Checked',
        'empty' => 'No one has voided more than the limit share of their sales.',
    ],

    'negative_stock' => [
        'branch' => 'Branch',
        'item' => 'Item',
        'quantity' => 'On hand',
        'empty' => 'No item is below zero.',
    ],

    'today' => [
        'sales' => 'Sales today',
        'vat' => 'Including :vat VAT',
        'orders' => 'Orders today',
        'voids_cancels' => 'Voids / cancellations',
        'voids_cancels_help' => 'A high share can point to till losses.',
        'etims' => 'eTIMS pending / failed',
        'etims_help' => 'Documents still owed to KRA.',
        'sales_by_hour' => 'Sales by hour today (Africa/Nairobi)',
        'sales_kes' => 'Sales (KES)',
        'no_sales' => 'No sales yet today.',
    ],

    'errors' => [
        'action_failed' => 'That could not be done.',
    ],
];
