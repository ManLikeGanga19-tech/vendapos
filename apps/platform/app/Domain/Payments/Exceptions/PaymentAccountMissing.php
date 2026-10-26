<?php

declare(strict_types=1);

namespace App\Domain\Payments\Exceptions;

use RuntimeException;

/** The branch has no account for the provider and the business has no default. */
final class PaymentAccountMissing extends RuntimeException
{
    public const string CODE = 'payment_account_missing';
}
