<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms;

final readonly class SmsResult
{
    private function __construct(
        public bool $accepted,
        public ?string $messageId,
        public ?string $error,
    ) {}

    public static function accepted(?string $messageId): self
    {
        return new self(true, $messageId, null);
    }

    public static function refused(string $error): self
    {
        return new self(false, null, $error);
    }
}
