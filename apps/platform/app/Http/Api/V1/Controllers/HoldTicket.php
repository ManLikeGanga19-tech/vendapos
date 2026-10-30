<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Data\DeviceContext;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Verticals\Actions\HoldTicketForPayment;
use App\Http\Api\V1\Requests\HoldTicketRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/tickets/{id}/hold — a till asks to take payment for a ticket
 * with a sale; refused (409) when the ticket is paid or another sale holds it,
 * 404 when the server does not have the ticket yet.
 * DELETE /api/v1/tickets/{id}/hold — the cashier took no payment after all.
 */
final class HoldTicket
{
    public function __invoke(HoldTicketRequest $request, string $ticketId, HoldTicketForPayment $holds): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');

        $outcome = $holds->hold($ticketId, (string) $request->validated('sale_uuid'), DeviceContext::of($device));

        return match ($outcome) {
            HoldTicketForPayment::HELD => response()->json(['status' => 'held']),
            HoldTicketForPayment::UNKNOWN => response()->json(['message' => 'This ticket is not on the server yet.', 'code' => $outcome], Response::HTTP_NOT_FOUND),
            default => response()->json(['message' => $outcome === HoldTicketForPayment::PAID
                ? 'This ticket is already paid.'
                : 'Another till is taking payment for this ticket.', 'code' => $outcome], Response::HTTP_CONFLICT),
        };
    }

    public function release(HoldTicketRequest $request, string $ticketId, HoldTicketForPayment $holds): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');

        $holds->release($ticketId, (string) $request->validated('sale_uuid'), DeviceContext::of($device));

        return response()->json(['status' => 'released']);
    }
}
