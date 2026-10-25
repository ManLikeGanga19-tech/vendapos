<?php

declare(strict_types=1);

namespace App\Staff;

use Carbon\CarbonImmutable;

final readonly class PinOutcome
{
    public const string ACCEPTED = 'accepted';

    public const string WRONG = 'wrong';

    public const string LOCKED = 'locked';

    private function __construct(
        public string $result,
        public int $triesLeft = 0,
        public ?CarbonImmutable $lockedUntil = null,
    ) {}

    public static function accepted(): self
    {
        return new self(self::ACCEPTED);
    }

    public static function wrong(int $triesLeft): self
    {
        return new self(self::WRONG, triesLeft: $triesLeft);
    }

    public static function locked(CarbonImmutable $until): self
    {
        return new self(self::LOCKED, lockedUntil: $until);
    }

    public function isAccepted(): bool
    {
        return $this->result === self::ACCEPTED;
    }
}
