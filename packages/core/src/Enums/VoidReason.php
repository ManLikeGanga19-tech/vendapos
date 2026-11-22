<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/**
 * Why a completed sale, or a sent ticket line, was voided: a fixed list, so
 * owners can compare tills and staff.
 */
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

    /**
     * Reasons for taking a sent line off a ticket: nothing is paid yet, so
     * "payment problem" does not apply (Daniel, T5c).
     *
     * @return list<self>
     */
    public static function forTicketLines(): array
    {
        return array_values(array_filter(self::cases(), fn (self $reason) => $reason !== self::PaymentProblem));
    }
}
