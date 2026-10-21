<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Payments\Models\Payment;
use App\Domain\Tenancy\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/v1/payments/{uuid} — a payment's state, for the device that asked for it only. */
final class ShowPayment
{
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');

        $payment = Payment::query()->whereKey($uuid)->where('device_id', $device->id)->firstOrFail();

        return response()->json(self::present($payment));
    }

    /** @return array<string, mixed> */
    public static function present(Payment $payment): array
    {
        return [
            'uuid' => $payment->id,
            'order_uuid' => $payment->order_id,
            'provider' => $payment->provider->value,
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
            'provider_reference' => $payment->provider_reference,
            'failure_code' => $payment->failure_code,
            'failure_reason' => $payment->failure_reason,
        ];
    }
}
