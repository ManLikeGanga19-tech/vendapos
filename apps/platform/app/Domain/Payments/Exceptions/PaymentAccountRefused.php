<?php

declare(strict_types=1);

namespace App\Domain\Payments\Exceptions;

use RuntimeException;

/**
 * A payment account change that was not saved. $reason keys
 * owner.payment_accounts.refused for the Owner panel; the message is for logs
 * and the console.
 */
final class PaymentAccountRefused extends RuntimeException
{
    private const array MESSAGES = [
        'unsupported_provider' => 'This provider is not supported yet.',
        'not_found' => 'That payment account does not exist.',
        'environment_mixed' => 'Every account of a business must use the same environment.',
        'default_required' => 'Make another account the default instead.',
        'keys_refused' => 'The provider refused these keys.',
        'unreachable' => 'The provider could not be reached to check these keys.',
    ];

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : (self::MESSAGES[$reason] ?? $reason));
    }
}
