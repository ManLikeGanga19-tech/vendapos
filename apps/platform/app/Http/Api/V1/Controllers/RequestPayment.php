<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Payments\Actions\RequestPayment as RequestPaymentAction;
use App\Domain\Payments\Exceptions\PaymentAccountMissing;
use App\Domain\Payments\Exceptions\PaymentConflict;
use App\Domain\Tenancy\Data\DeviceContext;
use App\Domain\Tenancy\Models\Device;
use App\Http\Api\V1\Requests\RequestPaymentRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use VendaPOS\Core\Data\PaymentRequestData;

/**
 * POST /api/v1/payments — ask for a mobile-money push to the customer's
 * phone. Replies 202 at once; the terminal then polls GET /payments/{uuid}.
 * Resending the same UUID and details replies 200 with the current state.
 */
final class RequestPayment
{
    public function __invoke(RequestPaymentRequest $request, RequestPaymentAction $requestPayment): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');

        try {
            [$payment, $created] = $requestPayment->handle(
                PaymentRequestData::from($request->validated()),
                DeviceContext::of($device),
            );
        } catch (PaymentConflict) {
            return response()->json(['message' => 'This payment UUID is already used for a different payment.'], Response::HTTP_CONFLICT);
        } catch (PaymentAccountMissing) {
            return response()->json([
                'message' => 'This branch has no account to take this payment into.',
                'code' => PaymentAccountMissing::CODE,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(ShowPayment::present($payment), $created ? Response::HTTP_ACCEPTED : Response::HTTP_OK);
    }
}
