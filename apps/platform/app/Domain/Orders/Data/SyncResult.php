<?php

declare(strict_types=1);

namespace App\Domain\Orders\Data;

use JsonSerializable;

/** The outcome for one order in a sync batch; one bad order never blocks the rest. */
final readonly class SyncResult implements JsonSerializable
{
    public const string ACCEPTED = 'accepted';

    public const string DUPLICATE = 'duplicate';

    public const string UPDATED = 'updated';

    public const string REJECTED = 'rejected';

    /** @param array<string, array<string>|string> $errors */
    private function __construct(
        public ?string $uuid,
        public string $status,
        public ?string $reason = null,
        public array $errors = [],
    ) {}

    public static function accepted(string $uuid): self
    {
        return new self($uuid, self::ACCEPTED);
    }

    public static function duplicate(string $uuid): self
    {
        return new self($uuid, self::DUPLICATE);
    }

    public static function updated(string $uuid): self
    {
        return new self($uuid, self::UPDATED);
    }

    /** @param array<string, array<string>|string> $errors */
    public static function rejected(?string $uuid, string $reason, array $errors = []): self
    {
        return new self($uuid, self::REJECTED, $reason, $errors);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return array_filter([
            'uuid' => $this->uuid,
            'status' => $this->status,
            'reason' => $this->reason,
            'errors' => $this->errors ?: null,
        ], fn ($value) => $value !== null);
    }
}
