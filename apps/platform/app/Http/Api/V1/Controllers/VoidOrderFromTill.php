<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Orders\Actions\VoidOrder;
use App\Domain\Orders\Data\SyncResult;
use App\Domain\Tenancy\Data\DeviceContext;
use App\Domain\Tenancy\Models\Device;
use App\Http\Api\V1\Requests\VoidOrderRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use VendaPOS\Core\Data\VoidData;

/**
 * POST /api/v1/orders/{orderId}/void — a till voids a sale of its branch it
 * may not have made (online only). 200 updated, or duplicate when it was
 * already voided; 404 for a sale of another branch or one the server does not
 * have yet; 409 for a sale that cannot be voided; 422 for unknown staff.
 * The till that made the sale may instead send the void with the order on sync.
 */
final class VoidOrderFromTill
{
    public function __invoke(VoidOrderRequest $request, string $orderId, VoidOrder $voidOrder): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');
        $result = $voidOrder->fromTill($orderId, VoidData::from($request->validated()), DeviceContext::of($device));

        $status = match (true) {
            $result->status !== SyncResult::REJECTED => Response::HTTP_OK,
            $result->reason === 'order_not_found' => Response::HTTP_NOT_FOUND,
            $result->reason === 'status_conflict' => Response::HTTP_CONFLICT,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };

        return response()->json($result, $status);
    }
}
