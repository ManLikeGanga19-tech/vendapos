<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Catalogue\Actions\FindUnknownItems;
use App\Domain\Orders\Data\SyncResult;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Events\OrderVoided;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderLine;
use App\Domain\Orders\Models\OrderTender;
use App\Domain\Orders\Support\ReceiptNumber;
use App\Domain\Payments\Actions\FindTenderPayments;
use App\Domain\Tenancy\Actions\FindUnknownStaff;
use App\Domain\Tenancy\Data\DeviceContext;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use VendaPOS\Core\Data\OrderData;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Payments\Tenders;
use VendaPOS\Core\Tax\OrderTotals;

/**
 * Durably records one synced sale (CLAUDE.md §3.2–3.3): order, lines and its
 * outbox event commit in one transaction, side effects run after commit, and
 * resending the same order UUID is a no-op.
 */
final class RecordOrder
{
    public function __construct(
        private readonly FindUnknownItems $findUnknownItems,
        private readonly FindTenderPayments $findTenderPayments,
        private readonly FindUnknownStaff $findUnknownStaff,
    ) {}

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

        if ($this->findUnknownStaff->handle([$data->staff_id]) !== []) {
            return SyncResult::rejected($data->uuid, 'unknown_staff', ['staff_id' => 'No one at this business has this ID.']);
        }

        $tenderProblems = Tenders::problems($data->tenderList(), $data->status, $data->total_cents);

        if ($tenderProblems !== []) {
            return SyncResult::rejected($data->uuid, 'tenders_mismatch', $tenderProblems);
        }

        $paymentProblems = $this->paymentProblems($data, $context);

        if ($paymentProblems !== []) {
            return SyncResult::rejected($data->uuid, 'payment_mismatch', $paymentProblems);
        }

        try {
            return DB::transaction(fn () => $this->write($data, $context));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request committed the same UUID, sequence number or payment first.
            if (Order::query()->whereKey($data->uuid)->exists()) {
                return SyncResult::duplicate($data->uuid);
            }

            $paymentProblems = $this->paymentProblems($data, $context);

            return $paymentProblems !== []
                ? SyncResult::rejected($data->uuid, 'payment_mismatch', $paymentProblems)
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
            'staff_id' => $data->staff_id,
            'ticket_id' => $data->ticket_uuid,
            'sequence_number' => $data->sequence_number,
            'receipt_number' => ReceiptNumber::format($context->tillNumber, $data->sequence_number),
            'status' => $data->status,
            'placed_at' => Carbon::parse($data->placed_at)->utc(),
            'payment_type' => $data->paymentType(),
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

        $order->setRelation('lines', $order->lines()->createMany($lines));

        $order->setRelation('tenders', $order->tenders()->createMany(array_map(fn (TenderData $tender, int $position) => [
            'position' => $position + 1,
            'method' => $tender->method,
            'amount_cents' => $tender->amount_cents,
            'tendered_cents' => $tender->tendered_cents,
            'change_cents' => $tender->change_cents,
            'payment_id' => $tender->payment_uuid,
            'mpesa_reference' => $tender->mpesa_reference,
        ], $data->tenderList(), array_keys($data->tenderList()))));

        $event = DomainEvent::record(
            type: 'order.recorded',
            tenantId: $context->tenantId,
            payload: $this->snapshot($order),
            aggregateId: $order->id,
            branchId: $context->branchId,
            deviceId: $context->deviceId,
        );

        OrderRecorded::dispatch($event->event_id, $context->tenantId, $order->id);

        return SyncResult::accepted($order->id);
    }

    /**
     * Lines are immutable once accepted. The only change a resend can make is
     * completed → voided. Resending the current status, or a stale "completed"
     * from a retried batch after a void, is a duplicate; any other change
     * (e.g. completed → cancelled) is a terminal bug and is rejected.
     */
    private function applyResend(Order $order, OrderData $data, DeviceContext $context): SyncResult
    {
        if ($order->device_id !== $context->deviceId) {
            return SyncResult::rejected($data->uuid, 'uuid_conflict');
        }

        if ($data->status === $order->status || $order->status->hasMovedPast($data->status)) {
            return SyncResult::duplicate($order->id);
        }

        if (! $order->status->canBecome($data->status)) {
            return SyncResult::rejected($data->uuid, 'status_conflict', [
                'status' => "The order is {$order->status->value} and cannot become {$data->status->value}.",
            ]);
        }

        $order->update(['status' => OrderStatus::Voided]);

        $event = DomainEvent::record(
            type: 'order.voided',
            tenantId: $context->tenantId,
            payload: $this->snapshot($order),
            aggregateId: $order->id,
            branchId: $context->branchId,
            deviceId: $context->deviceId,
        );

        OrderVoided::dispatch($event->event_id, $context->tenantId, $order->id);

        return SyncResult::updated($order->id);
    }

    /**
     * The outbox payload for every order event: a full snapshot, so each
     * listener can act on its own event without reading the Orders tables.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'staff_id' => $order->staff_id,
            'ticket_id' => $order->ticket_id,
            'sequence_number' => $order->sequence_number,
            'receipt_number' => $order->receipt_number,
            'status' => $order->status->value,
            'placed_at' => $order->placed_at->toIso8601String(),
            'payment_type' => $order->payment_type?->value,
            'subtotal_cents' => $order->subtotal_cents,
            'vat_cents' => $order->vat_cents,
            'total_cents' => $order->total_cents,
            'lines' => $order->lines->map(fn (OrderLine $line) => [
                'item_id' => $line->item_id,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unit_price_cents,
                'vat_category' => $line->vat_category->value,
                'line_total_cents' => $line->line_total_cents,
                'vat_cents' => $line->vat_cents,
            ])->values()->all(),
            'tenders' => $order->tenders->map(fn (OrderTender $tender) => [
                'method' => $tender->method->value,
                'amount_cents' => $tender->amount_cents,
                'tendered_cents' => $tender->tendered_cents,
                'change_cents' => $tender->change_cents,
                'payment_id' => $tender->payment_id,
                'mpesa_reference' => $tender->mpesa_reference,
            ])->values()->all(),
        ];
    }

    /**
     * Each M-Pesa push tender must name a payment this till started, for the
     * same amount, that succeeded and is not already paying another sale.
     *
     * @return array<string, string>
     */
    private function paymentProblems(OrderData $data, DeviceContext $context): array
    {
        $paymentIds = array_values(array_filter(array_map(fn (TenderData $tender) => $tender->payment_uuid, $data->tenderList())));

        if ($paymentIds === []) {
            return [];
        }

        $payments = $this->findTenderPayments->handle($paymentIds, $context->deviceId);
        $claimed = OrderTender::query()->whereIn('payment_id', $paymentIds)->where('order_id', '!=', $data->uuid)->pluck('payment_id')->all();
        $errors = [];

        foreach ($data->tenderList() as $index => $tender) {
            if ($tender->payment_uuid === null) {
                continue;
            }

            $payment = $payments[$tender->payment_uuid] ?? null;

            $problem = match (true) {
                $payment === null => 'No payment of this till has this UUID.',
                $payment['amount_cents'] !== $tender->amount_cents => "The payment is for {$payment['amount_cents']}, the tender for {$tender->amount_cents}.",
                ! $payment['succeeded'] => 'The payment has not succeeded.',
                $payment['order_id'] !== null && $payment['order_id'] !== $data->uuid => 'The payment was requested for another sale.',
                in_array($tender->payment_uuid, $claimed, true) => 'The payment already pays another sale.',
                default => null,
            };

            if ($problem !== null) {
                $errors["tenders.{$index}.payment_uuid"] = $problem;
            }
        }

        return $errors;
    }
}
