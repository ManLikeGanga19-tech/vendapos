<?php

declare(strict_types=1);

namespace App\Api\Data;

use Carbon\CarbonImmutable;

final readonly class IssuedToken
{
    public function __construct(
        public string $plainText,
        public ?CarbonImmutable $expiresAt,
    ) {}
}
