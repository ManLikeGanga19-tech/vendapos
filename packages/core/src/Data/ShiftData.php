<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use VendaPOS\Core\ValueObjects\Money;

/**
 * One till drawer's shift as the till sends it: the float counted at open,
 * the cash that left the drawer, and, once closed, the blind count and the
 * till's own expected figure. The server works the expected cash out again
 * from the synced sales and flags a difference; it never trusts this figure.
 * Tenant, branch and device come from the device token, never from here.
 */
final class ShiftData extends Data
{
    public const int NOTE_MAX = 200;

    public const int MAX_MOVEMENTS = 500;

    /** @param  DataCollection<int, CashMovementData>  $movements */
    public function __construct(
        // Client-generated; the idempotency key for sync.
        public string $uuid,
        // Per till, strictly increasing; printed as the Z report number.
        public int $number,
        public string $opened_at,
        public string $opened_by,
        public CashCountData $opening_float,
        #[DataCollectionOf(CashMovementData::class)]
        public DataCollection $movements,
        // Set together when the shift is closed.
        public ?string $closed_at = null,
        public ?string $closed_by = null,
        public ?CashCountData $closing_count = null,
        public ?int $expected_cents = null,
        // A manager's PIN and a note when the count was off by more than the business allows.
        public ?string $approved_by = null,
        public ?string $note = null,
    ) {}

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /** @return list<CashMovementData> */
    public function movementList(): array
    {
        return array_values($this->movements->all());
    }

    public function float(): Money
    {
        return $this->opening_float->total();
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $rules = [
            'uuid' => ['required', 'string', 'uuid'],
            'number' => ['required', 'integer', 'min:1'],
            'opened_at' => ['required', 'date'],
            'opened_by' => ['required', 'string', 'uuid'],
            'opening_float' => ['required', 'array:counts'],
            'movements' => ['present', 'array', 'max:'.self::MAX_MOVEMENTS],
            'closed_at' => ['nullable', 'date', 'after_or_equal:opened_at'],
            'closed_by' => ['nullable', 'string', 'uuid', 'required_with:closed_at', 'prohibited_if:closed_at,null'],
            'closing_count' => ['nullable', 'array:counts', 'required_with:closed_at', 'prohibited_if:closed_at,null'],
            // Negative only if more left the drawer than came in: kept, so the server can flag it.
            'expected_cents' => ['nullable', 'integer', 'required_with:closed_at', 'prohibited_if:closed_at,null'],
            'approved_by' => ['nullable', 'string', 'uuid', 'prohibited_if:closed_at,null'],
            'note' => ['nullable', 'string', 'max:'.self::NOTE_MAX, 'required_with:approved_by'],
        ];

        $rules += CashCountData::rules('opening_float.');

        foreach (CashCountData::rules('closing_count.') as $field => $countRules) {
            $rules[$field] = ['exclude_without:closed_at', ...$countRules];
        }

        return $rules + CashMovementData::rules('movements.*.');
    }
}
