<?php

declare(strict_types=1);

namespace App\Api\Data;

use Carbon\CarbonImmutable;

final readonly class DeviceStatus
{
    public function __construct(
        public string $deviceName,
        public BranchInfo $branch,
        public ?CarbonImmutable $tokenExpiresAt,
        /** "active" or "suspended": suspended still sells and syncs, but offers cash instead of M-Pesa. */
        public string $accountStatus,
    ) {}
}
