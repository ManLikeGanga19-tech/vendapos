<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Catalogue\Actions\FindUnknownItems;
use App\Domain\Orders\Data\DeviceContext;
use App\Domain\Orders\Data\SyncResult;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Events\OrderVoided;
use App\Domain\Orders\Models\Order;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use VendaPOS\Core\Data\OrderData;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Tax\OrderTotals;

/**
 * Durably records one synced sale (CLAUDE.md §3.2–3.3): order, lines and its
 * outbox event commit in one transaction, side effects run after commit, and
 * resending the same order UUID is a no-op.
 */
final class RecordOrder
{
    public function __construct(private readonly FindUnknownItems $findUnknownItems) {}

    public function handle(OrderData $data, DeviceContext $context): SyncResult
    {
        // The server never trusts the terminal's arithmetic (approved by Daniel, M1).
        $mismatches = OrderTotals::mismatches($data->lines, $data->subtotal_cents, $data->vat_cents, $data->total_cents);

        if ($mismatches !== []) {
            return SyncResult::rejected($data->uuid, 'totals_mismatch', $mismatches);
        }

        $unknownItems = $this->findUnknownItems->handle(array_values(array_map(
            fn (OrderLineData $line) => $line->item_id,
            $data->lines->all(),
        )));

        if ($unknownItems !== []) {
            return SyncResult::rejected($data->uuid, 'unknown_items', ['item_ids' => $unknownItems]);
        }

        try {
            return DB::transaction(fn () => $this->write($data, $context));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request committed the same UUID or sequence number first.
            return Order::query()->whereKey($data->uuid)->exists()
                ? SyncResult::duplicate($data->uuid)
                : SyncResult::rejected($data->uuid, 'sequence_conflict');
        }
    }

    private function write(OrderData $data, DeviceContext $context): SyncResult
    {
        $existing = Order::query()->whereKey($data->uuid)->lockForUpdate()->first();

        if ($existing !== null) {
            return $this->applyResend($existing, $data, $context);
        }

        $totals = OrderTotals::fromLines($data->lines);

        $order = Order::create([
            'id' => $data->uuid,
            'branch_id' => $context->branchId,
            'device_id' => $context->deviceId,
            'sequence_number' => $data->sequence_number,
            'status' => $data->status,
            'placed_at' => Carbon::parse($data->placed_at)->utc(),
            'payment_type' => $data->payment_type,
            'subtotal_cents' => $totals->subtotal->cents,
            'vat_cents' => $totals->vat->cents,
            'total_cents' => $totals->total->cents,
        ]);

        $lines = [];

        foreach ($data->lines->all() as $position => $line) {
            $lines[] = [
                'position' => $position + 1,
                'item_id' => $line->item_id,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unit_price_cents,
                'vat_category' => $line->vat_category,
                'line_total_cents' => $line->line_total_cents,
                'vat_cents' => $totals->lineVat[$position]->cents,
            ];
        }

        $order->lines()->createMany($lines);

        $event = DomainEvent::record(
            type: 'order.recorded',
            tenantId: $context->tenantId,
            payload: [
                'order_id' => $order->id,
                'sequence_number' => $order->sequence_number,
                'status' => $order->status->value,
                'placed_at' => $order->placed_at->toIso8601String(),
                'payment_type' => $order->payment_type->value,
                'subtotal_cents' => $order->subtotal_cents,
                'vat_cents' => $order->vat_cents,
                'total_cents' => $order->total_cents,
                'lines' => array_map(fn (array $line) => [
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'line_total_cents' => $line['line_total_cents'],
                    'vat_cents' => $line['vat_cents'],
                ], $lines),
            ],
            aggregateId: $order->id,
            branchId: $context->branchId,
            deviceId: $context->deviceId,
        );

        OrderRecorded::dispatch($event->event_id, $context->tenantId, $order->id);

        return SyncResult::accepted($order->id);
    }

    /**
     * Lines are immutable once accepted. The only change a resend can make is
     * completed → voided; a stale "completed" never undoes a void.
     */
    private function applyResend(Order $order, OrderData $data, DeviceContext $context): SyncResult
    {
        if ($order->device_id !== $context->deviceId) {
            return SyncResult::rejected($data->uuid, 'uuid_conflict');
        }

        if ($order->status !== OrderStatus::Completed || $data->status !== OrderStatus::Voided) {
            return SyncResult::duplicate($order->id);
        }

        $order->update(['status' => OrderStatus::Voided]);

        $event = DomainEvent::record(
            type: 'order.voided',
            tenantId: $context->tenantId,
            payload: ['order_id' => $order->id, 'total_cents' => $order->total_cents],
            aggregateId: $order->id,
            branchId: $context->branchId,
            deviceId: $context->deviceId,
        );

        OrderVoided::dispatch($event->event_id, $context->tenantId, $order->id);

        return SyncResult::updated($order->id);
    }
}
