<?php

declare(strict_types=1);

namespace App\Domain\Orders\Data;

/**
 * Where a sale came from, taken from the authenticated device (never from
 * the request body) and handed to the Orders module by the HTTP layer.
 */
final readonly class DeviceContext
{
    public function __construct(
        public string $tenantId,
        public string $branchId,
        public string $deviceId,
    ) {}
}
