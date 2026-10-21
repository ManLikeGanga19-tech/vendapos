<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Orders\Actions\RecordOrder;
use App\Domain\Orders\Data\SyncResult;
use App\Domain\Tenancy\Data\DeviceContext;
use App\Domain\Tenancy\Models\Device;
use App\Http\Api\V1\Requests\SyncOrdersRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\OrderData;
use VendaPOS\Core\Data\OrderLineData;

/**
 * POST /api/v1/orders/sync — a batch of up to 50 terminal orders. The reply
 * lists one result per order (accepted, duplicate, updated or rejected with a
 * reason), so the terminal can mark each one off its offline queue.
 */
final class SyncOrders
{
    public function __invoke(SyncOrdersRequest $request, RecordOrder $recordOrder): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');
        $context = new DeviceContext($device->tenant_id, $device->branch_id, $device->id);

        /** @var list<array<string, mixed>> $orders */
        $orders = $request->validated('orders');

        $results = array_map(fn (array $raw) => $this->syncOne($raw, $context, $recordOrder), $orders);

        return response()->json(['results' => $results]);
    }

    /** @param array<string, mixed> $raw */
    private function syncOne(array $raw, DeviceContext $context, RecordOrder $recordOrder): SyncResult
    {
        $uuid = is_string($raw['uuid'] ?? null) ? $raw['uuid'] : null;
        $validator = Validator::make($raw, OrderData::validationRules());
        $errors = $validator->errors()->toArray();

        // Unknown keys are rejected at both levels (tenant_id in the body included).
        foreach (array_diff(array_keys($raw), array_keys(OrderData::rules())) as $key) {
            $errors[(string) $key] = ['Unknown field.'];
        }

        foreach (is_array($raw['lines'] ?? null) ? $raw['lines'] : [] as $index => $line) {
            foreach (array_diff(array_keys(is_array($line) ? $line : []), array_keys(OrderLineData::rules())) as $key) {
                $errors["lines.{$index}.{$key}"] = ['Unknown field.'];
            }
        }

        if ($errors !== []) {
            return SyncResult::rejected($uuid, 'invalid', $errors);
        }

        return $recordOrder->handle(OrderData::from($validator->validated()), $context);
    }
}
