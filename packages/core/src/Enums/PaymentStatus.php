<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/** Where a mobile-money payment stands. Only Pending ever changes. */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
