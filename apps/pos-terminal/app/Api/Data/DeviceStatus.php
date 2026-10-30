<?php

declare(strict_types=1);

namespace App\Api\Data;

use Carbon\CarbonImmutable;

final readonly class DeviceStatus
{
    /**
     * @param  array{key: string, host: string, port: int, scheme: string, channel: string, event: string}|null  $realtime  where to listen for "sync now"; null while the server has it off
     */
    public function __construct(
        public string $deviceName,
        public BranchInfo $branch,
        public ?CarbonImmutable $tokenExpiresAt,
        /** "active" or "suspended": suspended still sells and syncs, but offers cash instead of M-Pesa. */
        public string $accountStatus,
        public ?array $realtime = null,
        public ?string $tillCode = null,
    ) {}
}
