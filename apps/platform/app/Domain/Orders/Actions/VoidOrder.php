<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Data\SyncResult;
use App\Domain\Orders\Events\OrderVoided;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderVoid;
use App\Domain\Orders\Support\OrderSnapshot;
use App\Domain\Tenancy\Actions\FindUnknownStaff;
use App\Domain\Tenancy\Actions\StaffMayAtBranch;
use App\Domain\Tenancy\Data\DeviceContext;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use VendaPOS\Core\Data\VoidData;
use VendaPOS\Core\Enums\OrderStatus;

/**
 * Voids a completed sale and records who voided it, who approved it and why.
 * The till that made the sale sends the void with the order (it may have been
 * offline); any other till in the branch voids it here while online.
 *
 * The till already checked the approver's PIN and permission, and the money
 * has already gone back, so the server never refuses a void for the approver
 * or the day: it records what it found for the owner to look at.
 */
final class VoidOrder
{
    /** The permission a till asks the approver's PIN for. */
    public const string PERMISSION = 'orders.void';

    public function __construct(
        private readonly FindUnknownStaff $findUnknownStaff,
        private readonly StaffMayAtBranch $staffMayAtBranch,
    ) {}

    /**
     * What is wrong with a void before anything is written, or null.
     *
     * @return array{reason: string, errors: array<string, string>}|null
     */
    public function problems(VoidData $void, string $placedAt, string $prefix = ''): ?array
    {
        $unknown = $this->findUnknownStaff->handle([$void->voided_by, $void->approved_by]);

        if ($unknown !== []) {
            $errors = [];

            foreach (['voided_by', 'approved_by'] as $field) {
                if (in_array($void->{$field}, $unknown, true)) {
                    $errors["{$prefix}{$field}"] = 'No one at this business has this ID.';
                }
            }

            return ['reason' => 'unknown_staff', 'errors' => $errors];
        }

        if (Carbon::parse($void->voided_at)->lt(Carbon::parse($placedAt))) {
            return ['reason' => 'invalid', 'errors' => ["{$prefix}voided_at" => 'A sale cannot be voided before it was made.']];
        }

        return null;
    }

    /**
     * Writes the void's record for an order already marked voided. Call inside
     * the transaction that changes the order.
     */
    public function record(Order $order, VoidData $void, string $deviceId): OrderVoid
    {
        $timezone = (string) config('vendapos.business_timezone');
        $voidedAt = Carbon::parse($void->voided_at)->utc();

        $record = OrderVoid::create([
            'order_id' => $order->id,
            'device_id' => $deviceId,
            'voided_at' => $voidedAt,
            'voided_by' => $void->voided_by,
            'approved_by' => $void->approved_by,
            'reason' => $void->reason,
            'note' => $void->note,
            'approver_allowed' => $this->staffMayAtBranch->handle($void->approved_by, self::PERMISSION, $order->branch_id),
            'same_business_day' => $voidedAt->copy()->setTimezone($timezone)->toDateString()
                === $order->placed_at->copy()->setTimezone($timezone)->toDateString(),
        ]);

        $order->setRelation('void', $record);

        return $record;
    }

    /**
     * completed → voided, with its record and outbox event. Call inside a
     * transaction, with the order locked.
     */
    public function apply(Order $order, VoidData $void, DeviceContext $context): SyncResult
    {
        $order->update(['status' => OrderStatus::Voided]);
        $this->record($order, $void, $context->deviceId);

        $event = DomainEvent::record(
            type: 'order.voided',
            tenantId: $context->tenantId,
            payload: OrderSnapshot::of($order),
            aggregateId: $order->id,
            branchId: $order->branch_id,
            deviceId: $context->deviceId,
        );

        OrderVoided::dispatch($event->event_id, $context->tenantId, $order->id);

        return SyncResult::updated($order->id);
    }

    /**
     * A till voids a sale it may not have made: only a sale of its own branch,
     * and only once. Unknown and other-branch sales look the same.
     */
    public function fromTill(string $orderId, VoidData $void, DeviceContext $context): SyncResult
    {
        try {
            return DB::transaction(function () use ($orderId, $void, $context) {
                $order = Order::query()->whereKey($orderId)->where('branch_id', $context->branchId)->lockForUpdate()->first();

                if ($order === null) {
                    return SyncResult::rejected($orderId, 'order_not_found');
                }

                if ($order->status === OrderStatus::Voided) {
                    return SyncResult::duplicate($order->id);
                }

                if (! $order->status->canBecome(OrderStatus::Voided)) {
                    return SyncResult::rejected($order->id, 'status_conflict', [
                        'status' => "The order is {$order->status->value} and cannot become voided.",
                    ]);
                }

                $problems = $this->problems($void, $order->placed_at->toIso8601String());

                if ($problems !== null) {
                    return SyncResult::rejected($order->id, $problems['reason'], $problems['errors']);
                }

                return $this->apply($order, $void, $context);
            });
        } catch (UniqueConstraintViolationException) {
            // Two tills voided it at the same moment; the first one stands.
            return SyncResult::duplicate($orderId);
        }
    }
}
