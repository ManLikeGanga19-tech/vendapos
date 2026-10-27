<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Data;

use JsonSerializable;

/**
 * The outcome for one ticket in a sync batch; one bad ticket never blocks the
 * rest. A closed ticket refuses new lines: they were added after it was paid,
 * so the till must show them as not paid.
 */
final readonly class TicketSyncResult implements JsonSerializable
{
    public const string ACCEPTED = 'accepted';

    public const string UPDATED = 'updated';

    public const string DUPLICATE = 'duplicate';

    public const string REJECTED = 'rejected';

    /**
     * @param  array<string, array<string>|string>  $errors
     * @param  list<string>  $refusedLines
     */
    private function __construct(
        public ?string $uuid,
        public string $status,
        public ?string $ticketStatus = null,
        public array $refusedLines = [],
        public ?string $reason = null,
        public array $errors = [],
    ) {}

    /** @param  list<string>  $refusedLines */
    public static function saved(string $uuid, string $status, string $ticketStatus, array $refusedLines = []): self
    {
        return new self($uuid, $status, $ticketStatus, $refusedLines);
    }

    /** @param  array<string, array<string>|string>  $errors */
    public static function rejected(?string $uuid, string $reason, array $errors = []): self
    {
        return new self($uuid, self::REJECTED, reason: $reason, errors: $errors);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return array_filter([
            'uuid' => $this->uuid,
            'status' => $this->status,
            'ticket_status' => $this->ticketStatus,
            'refused_lines' => $this->refusedLines ?: null,
            'reason' => $this->reason,
            'errors' => $this->errors ?: null,
        ], fn ($value) => $value !== null);
    }
}
