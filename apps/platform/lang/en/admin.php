<?php

declare(strict_types=1);

return [
    'tenant' => [
        'overview' => 'Tenant',
        'branches' => 'Branches',
        'devices' => 'Devices',
        'etims' => 'eTIMS',
        'negative_stock' => 'Negative stock',
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
