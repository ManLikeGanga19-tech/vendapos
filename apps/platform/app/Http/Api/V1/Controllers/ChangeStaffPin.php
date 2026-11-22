<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Actions\ChangeOwnPin;
use App\Domain\Tenancy\Actions\ListTillStaff;
use App\Domain\Tenancy\Exceptions\PinChangeRefused;
use App\Domain\Tenancy\Models\Device;
use App\Http\Api\V1\Requests\ChangeStaffPinRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/staff/{staffId}/pin: someone on this till's staff list chooses
 * their own PIN. Answers with their new staff-list entry so the till can
 * update its copy straight away.
 */
final class ChangeStaffPin
{
    public function __invoke(ChangeStaffPinRequest $request, string $staffId, ChangeOwnPin $changeOwnPin, ListTillStaff $listTillStaff): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        try {
            $changeOwnPin->handle($device, $staffId, (string) $request->string('current_pin'), (string) $request->string('new_pin'));
        } catch (PinChangeRefused $e) {
            Log::channel('security')->notice("api.pin_change_{$e->reason}", ['tenant_id' => tenant('id'), 'device_id' => $device->id, 'staff_id' => $staffId]);

            return match ($e->reason) {
                // Same answer as a missing person, so a till cannot probe who exists elsewhere.
                PinChangeRefused::NOT_FOUND => response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND),
                PinChangeRefused::TOO_MANY_TRIES => response()->json(['message' => 'Too many wrong PINs. Try again later.', 'reason' => $e->reason], Response::HTTP_TOO_MANY_REQUESTS),
                default => response()->json([
                    'message' => 'The PIN was not changed.',
                    'reason' => $e->reason,
                    'errors' => [$e->reason === PinChangeRefused::WRONG_PIN ? 'current_pin' : 'new_pin' => [$e->reason]],
                ], Response::HTTP_UNPROCESSABLE_ENTITY),
            };
        }

        Log::channel('security')->info('api.pin_changed', ['tenant_id' => tenant('id'), 'device_id' => $device->id, 'staff_id' => $staffId]);

        $entry = collect($listTillStaff->handle($device->branch_id))->firstWhere('id', $staffId);

        return response()
            ->json(['staff' => $entry])
            ->header('Cache-Control', 'no-store');
    }
}
