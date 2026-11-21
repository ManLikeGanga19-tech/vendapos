<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use VendaPOS\Core\Enums\VoidReason;

/**
 * Who voided a completed sale, who approved it and why. The till checks the
 * approver's PIN and permission offline; the server checks again after sync
 * and flags, never refuses, a void it would not have allowed: by then the
 * money has already gone back over the counter.
 */
final class VoidData extends Data
{
    public const int NOTE_MAX = 200;

    public function __construct(
        // When the void happened on the till, ISO 8601.
        public string $voided_at,
        // The signed-in staff member who voided it.
        public string $voided_by,
        // Who entered their PIN to approve it; may be the same person (a manager on their own).
        public string $approved_by,
        public VoidReason $reason,
        public ?string $note = null,
    ) {}

    /**
     * Rules for a void on its own, or nested under $prefix (e.g. "void.") in a larger payload.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}voided_at" => ['required', 'date'],
            "{$prefix}voided_by" => ['required', 'string', 'uuid'],
            "{$prefix}approved_by" => ['required', 'string', 'uuid'],
            "{$prefix}reason" => ['required', Rule::enum(VoidReason::class)],
            "{$prefix}note" => ['nullable', 'string', 'max:'.self::NOTE_MAX, "required_if:{$prefix}reason,".VoidReason::Other->value],
        ];
    }

    /** @return list<string> */
    public static function fields(): array
    {
        return ['voided_at', 'voided_by', 'approved_by', 'reason', 'note'];
    }
}
