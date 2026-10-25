<?php

declare(strict_types=1);

namespace App\Api\Data;

final readonly class BranchInfo
{
    public function __construct(
        public string $id,
        public string $name,
        public string $businessType,
        public string $terminalMode,
    ) {}
}
