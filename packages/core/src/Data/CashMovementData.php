<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use VendaPOS\Core\Enums\CashMovementKind;
use VendaPOS\Core\ValueObjects\Money;

/**
 * Cash that left a till's drawer during a shift, other than change: a
 * pay-out, a drop to the safe or a void refund. Recorded on the till first
 * and sent with its shift; the UUID makes a resend harmless.
 */
final class CashMovementData extends Data
{
    public const int REASON_MAX = 200;

    public function __construct(
        // Client-generated; the idempotency key for sync.
        public string $uuid,
        public CashMovementKind $kind,
        public int $amount_cents,
        // When the cash left the drawer, ISO 8601.
        public string $moved_at,
        // The signed-in staff member who took it out.
        public string $staff_id,
        // The manager whose PIN approved a pay-out or drop; may be the same person.
        public ?string $approved_by = null,
        // What a pay-out was spent on.
        public ?string $reason = null,
        // The voided sale a refund was for.
        public ?string $order_uuid = null,
    ) {}

    public function amount(): Money
    {
        return Money::fromCents($this->amount_cents);
    }

    /**
     * Rules for a movement on its own, or nested under $prefix (e.g. "movements.*.").
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        $kind = "{$prefix}kind";
        $voidRefund = CashMovementKind::VoidRefund->value;

        return [
            "{$prefix}uuid" => ['required', 'string', 'uuid'],
            $kind => ['required', Rule::enum(CashMovementKind::class)],
            "{$prefix}amount_cents" => ['required', 'integer', 'min:1'],
            "{$prefix}moved_at" => ['required', 'date'],
            "{$prefix}staff_id" => ['required', 'string', 'uuid'],
            "{$prefix}approved_by" => ['nullable', 'string', 'uuid', "required_unless:{$kind},{$voidRefund}"],
            "{$prefix}reason" => ['nullable', 'string', 'max:'.self::REASON_MAX, "required_if:{$kind},".CashMovementKind::PayOut->value],
            "{$prefix}order_uuid" => ['nullable', 'string', 'uuid', "required_if:{$kind},{$voidRefund}", "prohibited_unless:{$kind},{$voidRefund}"],
        ];
    }
}
