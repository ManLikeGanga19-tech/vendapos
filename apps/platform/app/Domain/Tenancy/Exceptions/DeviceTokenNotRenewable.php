<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

/**
 * An expired device token cannot be renewed. $reason is for the security
 * log only; the caller is never told which check failed.
 */
final class DeviceTokenNotRenewable extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?string $deviceId = null)
    {
        parent::__construct("Device token not renewable: {$reason}");
    }
}
