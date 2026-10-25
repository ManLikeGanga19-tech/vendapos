<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/** Where an order's KRA eTIMS document stands. */
enum EtimsStatus: string
{
    // Waiting to be sent, or failed and waiting for a retry.
    case Pending = 'pending';
    // eTIMS is not switched on for the branch.
    case NotRequired = 'not_required';
    case Submitted = 'submitted';
    // Gave up retrying; needs attention in the Admin panel.
    case Failed = 'failed';

    /** Nothing more will be sent for a document in this state without a manual retry. */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
