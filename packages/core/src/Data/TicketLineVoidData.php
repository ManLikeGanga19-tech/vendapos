<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use VendaPOS\Core\Enums\VoidReason;

/**
 * Some or all of a sent ticket line taken off before the ticket is paid,
 * approved with a manager's or owner's PIN (Daniel, T5c). A line may be cut
 * down more than once ("one Tusker less", then another), never below zero.
 * Like a sale void, the server flags rather than refuses a void it would not
 * have allowed: the kitchen has already been told.
 */
final class TicketLineVoidData extends Data
{
    public function __construct(
        // Client-generated; resending the same void takes nothing more off.
        public string $uuid,
        public string $line_uuid,
        // How many of the line's quantity come off.
        public int $quantity,
        public string $voided_at,
        // The signed-in staff member, a waiter included.
        public string $voided_by,
        // Who entered their PIN to approve it.
        public string $approved_by,
        public VoidReason $reason,
        public ?string $note = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid'],
            'line_uuid' => ['required', 'string', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'voided_at' => ['required', 'date'],
            'voided_by' => ['required', 'string', 'uuid'],
            'approved_by' => ['required', 'string', 'uuid'],
            'reason' => ['required', Rule::enum(VoidReason::class)->only(VoidReason::forTicketLines())],
            'note' => ['nullable', 'string', 'max:'.VoidData::NOTE_MAX, 'required_if:reason,'.VoidReason::Other->value],
        ];
    }
}
