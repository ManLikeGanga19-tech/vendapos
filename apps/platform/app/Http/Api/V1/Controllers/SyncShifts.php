<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\CashUp\Actions\RecordShift;
use App\Domain\Orders\Data\SyncResult;
use App\Domain\Tenancy\Data\DeviceContext;
use App\Domain\Tenancy\Models\Device;
use App\Http\Api\V1\Requests\SyncShiftsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\CashMovementData;
use VendaPOS\Core\Data\ShiftData;

/**
 * POST /api/v1/shifts/sync — up to 50 of this till's drawer shifts, each
 * with the cash movements not yet accepted. Tills send shifts before their
 * sales. The reply lists one result per shift (accepted, updated, duplicate
 * or rejected with a reason) so the till can mark each one off.
 */
final class SyncShifts
{
    private const array COUNT_FIELDS = ['counts'];

    public function __invoke(SyncShiftsRequest $request, RecordShift $recordShift): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');
        $context = DeviceContext::of($device);

        /** @var list<array<string, mixed>> $shifts */
        $shifts = $request->validated('shifts');

        return response()->json([
            'results' => array_map(fn (array $raw) => $this->syncOne($raw, $context, $recordShift), $shifts),
        ]);
    }

    /** @param array<string, mixed> $raw */
    private function syncOne(array $raw, DeviceContext $context, RecordShift $recordShift): SyncResult
    {
        $uuid = is_string($raw['uuid'] ?? null) ? $raw['uuid'] : null;
        $validator = Validator::make($raw, ShiftData::rules());
        $errors = $validator->errors()->toArray();

        // Unknown keys are refused at every level (tenant or branch IDs included).
        $topLevel = array_unique(array_map(fn (string $rule) => explode('.', $rule)[0], array_keys(ShiftData::rules())));

        foreach (array_diff(array_keys($raw), $topLevel) as $key) {
            $errors[(string) $key] = ['Unknown field.'];
        }

        foreach (['opening_float', 'closing_count'] as $count) {
            foreach (array_diff(array_keys(is_array($raw[$count] ?? null) ? $raw[$count] : []), self::COUNT_FIELDS) as $key) {
                $errors["{$count}.{$key}"] = ['Unknown field.'];
            }
        }

        $movementFields = array_keys(CashMovementData::rules());

        foreach (is_array($raw['movements'] ?? null) ? $raw['movements'] : [] as $index => $movement) {
            foreach (array_diff(array_keys(is_array($movement) ? $movement : []), $movementFields) as $key) {
                $errors["movements.{$index}.{$key}"] = ['Unknown field.'];
            }
        }

        if ($errors !== []) {
            return SyncResult::rejected($uuid, 'invalid', $errors);
        }

        return $recordShift->handle(ShiftData::from($validator->validated()), $context);
    }
}
