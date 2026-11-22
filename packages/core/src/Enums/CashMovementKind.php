<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/**
 * Cash taken out of a till's drawer during a shift other than change for a
 * sale. Every kind takes cash out; the expected cash at close goes down by it.
 */
enum CashMovementKind: string
{
    // Spent on the business from the drawer (e.g. buying charcoal); needs a reason.
    case PayOut = 'pay_out';
    // Moved to the safe mid-shift so less cash sits in the drawer.
    case Drop = 'drop';
    // Given back to a customer for a voided cash sale.
    case VoidRefund = 'void_refund';

    /** Pay-outs and drops need a manager's PIN; a void refund was approved with its void. */
    public function needsApprover(): bool
    {
        return $this !== self::VoidRefund;
    }

    public function needsReason(): bool
    {
        return $this === self::PayOut;
    }
}
