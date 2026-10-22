<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/** State of a sale as recorded by the terminal. Last write wins on sync (architecture §12). */
enum OrderStatus: string
{
    case Completed = 'completed';
    // A completed sale (receipt issued) reversed afterwards; reported to eTIMS as sale + credit note.
    case Voided = 'voided';
    // Dropped before it was completed (no payment, no receipt): never a sale. Only valid on first sync.
    case Cancelled = 'cancelled';

    /** Whether a resend may move an order from this status to $next. */
    public function canBecome(self $next): bool
    {
        return $this === self::Completed && $next === self::Voided;
    }

    /** True when $resent is an older state this order has already moved past (a retried batch). */
    public function hasMovedPast(self $resent): bool
    {
        return $this === self::Voided && $resent === self::Completed;
    }
}
