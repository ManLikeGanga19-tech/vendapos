<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/** Why a completed sale was voided: a fixed list, so owners can compare tills and staff. */
enum VoidReason: string
{
    case WrongItem = 'wrong_item';
    case ChangedMind = 'changed_mind';
    case Duplicate = 'duplicate';
    case PaymentProblem = 'payment_problem';
    // Needs a note saying what happened.
    case Other = 'other';

    public function needsNote(): bool
    {
        return $this === self::Other;
    }
}
