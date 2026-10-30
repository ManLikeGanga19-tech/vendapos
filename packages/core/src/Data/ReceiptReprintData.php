<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Spatie\LaravelData\Data;

/**
 * A receipt printed again at a till, as the till reports it: kept for audit,
 * since repeated reprints are a common way to cheat. Tenant, branch and
 * device come from the device token, never from the body (CLAUDE.md §3.1).
 */
final class ReceiptReprintData extends Data
{
    public function __construct(
        // Client-generated; the idempotency key.
        public string $uuid,
        // The sale whose receipt was printed again.
        public string $order_uuid,
        // Who asked for it.
        public string $staff_id,
        public string $reprinted_at,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid'],
            'order_uuid' => ['required', 'string', 'uuid'],
            'staff_id' => ['required', 'string', 'uuid'],
            'reprinted_at' => ['required', 'date'],
        ];
    }
}
