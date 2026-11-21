<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderVoid;
use App\Domain\Orders\Models\VoidCheck;
use Illuminate\Support\Carbon;

/**
 * Opens a void check for the owner when a void the server has just recorded
 * is not one it would have allowed (T5a-2). Never refuses or undoes the void.
 */
final class FlagVoid
{
    /** A sale void: approved by someone who may not approve voids there, or on a later business day. */
    public function sale(Order $order, OrderVoid $void): void
    {
        $attributes = [
            'branch_id' => $order->branch_id,
            'order_id' => $order->id,
            'staff_id' => $void->voided_by,
            'approved_by' => $void->approved_by,
            'business_date' => self::businessDate($void->voided_at),
            'details' => [
                'receipt_number' => $order->receipt_number,
                'total_cents' => $order->total_cents,
                'reason' => $void->reason->value,
                'sold_on' => self::businessDate($order->placed_at),
            ],
        ];

        if (! $void->approver_allowed) {
            VoidCheck::open(VoidCheck::APPROVER_NOT_ALLOWED, "order:{$order->id}", $attributes);
        }

        if (! $void->same_business_day) {
            VoidCheck::open(VoidCheck::LATE_VOID, "order:{$order->id}", $attributes);
        }
    }

    /**
     * Some of a ticket line taken off, approved by someone who may not approve
     * voids at that branch (the server records it, since the kitchen was told).
     */
    public function ticketLine(string $voidId, string $ticketId, string $branchId, string $voidedBy, string $approvedBy, Carbon $voidedAt, string $item, int $quantity, string $reason): void
    {
        VoidCheck::open(VoidCheck::APPROVER_NOT_ALLOWED, "line:{$voidId}", [
            'branch_id' => $branchId,
            'ticket_id' => $ticketId,
            'staff_id' => $voidedBy,
            'approved_by' => $approvedBy,
            'business_date' => self::businessDate($voidedAt),
            'details' => ['item' => $item, 'quantity' => $quantity, 'reason' => $reason],
        ]);
    }

    private static function businessDate(Carbon $moment): string
    {
        return $moment->copy()->setTimezone((string) config('vendapos.business_timezone'))->toDateString();
    }
}
