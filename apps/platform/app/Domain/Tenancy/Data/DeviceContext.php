<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Data;

use App\Domain\Tenancy\Models\Device;

/**
 * Where a sale came from, taken from the authenticated device (never from
 * the request body) and handed to the domain modules by the HTTP layer.
 */
final readonly class DeviceContext
{
    public function __construct(
        public string $tenantId,
        public string $branchId,
        public string $deviceId,
        public int $tillNumber,
    ) {}

    public static function of(Device $device): self
    {
        return new self($device->tenant_id, $device->branch_id, $device->id, $device->till_number);
    }
}
