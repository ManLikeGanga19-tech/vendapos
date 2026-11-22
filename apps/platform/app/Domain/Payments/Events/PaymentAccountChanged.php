<?php

declare(strict_types=1);

namespace App\Domain\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** An account's keys, default or branches changed and it is committed; the owner is told. */
final class PaymentAccountChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $accountId,
        public readonly string $accountName,
        public readonly string $action,
        public readonly ?string $changedBy,
    ) {}
}
