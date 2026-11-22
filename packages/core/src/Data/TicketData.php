<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * An open order at a table or the counter (table service), as a till sends
 * it. A till sends the lines it knows; the server keeps every line it has
 * ever accepted, so lines are only ever added. Tenant, branch and device come
 * from the device token, never from the body (CLAUDE.md §3.1).
 */
final class TicketData extends Data
{
    /** Lines one ticket may hold, the same as one sale. */
    public const int MAX_LINES = 500;

    /**
     * @param  DataCollection<int, TicketLineData>  $lines
     * @param  DataCollection<int, TicketLineVoidData>|null  $voids
     */
    public function __construct(
        // Client-generated; the idempotency key.
        public string $uuid,
        // Who opened it.
        public string $staff_id,
        public string $opened_at,
        // A table of this branch, or null for takeaway / counter.
        public ?string $table_id,
        // Takeaway or counter name, e.g. "Otieno"; optional.
        public ?string $label,
        #[DataCollectionOf(TicketLineData::class)]
        public DataCollection $lines,
        // When the bill was printed for the guest; null while still ordering.
        public ?string $bill_requested_at = null,
        // Lines (or part of them) a manager took off; only ever added, like lines.
        #[DataCollectionOf(TicketLineVoidData::class)]
        public ?DataCollection $voids = null,
    ) {}

    /** @return list<TicketLineData> */
    public function lineList(): array
    {
        return array_values($this->lines->all());
    }

    /** @return list<TicketLineVoidData> */
    public function voidList(): array
    {
        return $this->voids === null ? [] : array_values($this->voids->all());
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid'],
            'staff_id' => ['required', 'string', 'uuid'],
            'opened_at' => ['required', 'date'],
            'table_id' => ['present', 'nullable', 'string', 'uuid'],
            'label' => ['present', 'nullable', 'string', 'max:40'],
            'lines' => ['present', 'array', 'max:'.self::MAX_LINES],
            'bill_requested_at' => ['sometimes', 'nullable', 'date'],
            'voids' => ['sometimes', 'array', 'max:'.self::MAX_LINES],
        ];
    }

    /**
     * Ticket and line rules together, for validating one raw ticket payload.
     *
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        $rules = self::rules();

        foreach (TicketLineData::rules() as $field => $lineRules) {
            $rules["lines.*.{$field}"] = $lineRules;
        }

        foreach (TicketLineVoidData::rules() as $field => $voidRules) {
            // required_if names a sibling field: point it at the same void.
            $rules["voids.*.{$field}"] = array_map(
                fn (mixed $rule) => is_string($rule) && str_starts_with($rule, 'required_if:reason,') ? str_replace('required_if:reason,', 'required_if:voids.*.reason,', $rule) : $rule,
                $voidRules,
            );
        }

        return $rules;
    }
}
