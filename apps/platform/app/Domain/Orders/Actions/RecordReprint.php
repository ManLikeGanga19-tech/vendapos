<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Data\SyncResult;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\ReceiptReprint;
use App\Domain\Tenancy\Actions\FindUnknownStaff;
use App\Domain\Tenancy\Data\DeviceContext;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Data\ReceiptReprintData;

/**
 * Keeps one reprint a till reports. A till only holds its own sales, so the
 * sale must be one this till recorded; tills push sales before reprints, so
 * an unknown sale is a till bug, not a wait. Resending the same UUID is a no-op.
 */
final class RecordReprint
{
    public function __construct(private readonly FindUnknownStaff $findUnknownStaff) {}

    public function handle(ReceiptReprintData $data, DeviceContext $context): SyncResult
    {
        if (! Order::query()->whereKey($data->order_uuid)->where('device_id', $context->deviceId)->exists()) {
            return SyncResult::rejected($data->uuid, 'unknown_order', ['order_uuid' => 'This till has no sale with this UUID.']);
        }

        if ($this->findUnknownStaff->handle([$data->staff_id]) !== []) {
            return SyncResult::rejected($data->uuid, 'unknown_staff', ['staff_id' => 'No one at this business has this ID.']);
        }

        $inserted = ReceiptReprint::query()->insertOrIgnore([
            'id' => $data->uuid,
            'order_id' => $data->order_uuid,
            'device_id' => $context->deviceId,
            'staff_id' => $data->staff_id,
            'reprinted_at' => Carbon::parse($data->reprinted_at)->utc(),
            'created_at' => now(),
        ]);

        if ($inserted === 1) {
            return SyncResult::accepted($data->uuid);
        }

        // The same UUID from another till, or for another sale, is a clash, not a resend.
        $existing = ReceiptReprint::query()->findOrFail($data->uuid);

        return $existing->device_id === $context->deviceId && $existing->order_id === $data->order_uuid
            ? SyncResult::duplicate($data->uuid)
            : SyncResult::rejected($data->uuid, 'uuid_conflict');
    }
}
