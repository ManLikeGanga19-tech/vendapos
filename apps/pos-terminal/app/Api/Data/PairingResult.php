<?php

declare(strict_types=1);

namespace App\Api\Data;

final readonly class PairingResult
{
    public function __construct(
        public string $deviceId,
        public string $deviceName,
        public string $businessName,
        public BranchInfo $branch,
        public IssuedToken $token,
        // "T2": this till's code in its business, the start of its receipt numbers.
        public ?string $tillCode = null,
    ) {}
}
