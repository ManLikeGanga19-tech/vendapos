<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Orders\Actions\RecordReprint;
use App\Domain\Orders\Data\SyncResult;
use App\Domain\Tenancy\Data\DeviceContext;
use App\Domain\Tenancy\Models\Device;
use App\Http\Api\V1\Requests\SyncReprintsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\ReceiptReprintData;

/**
 * POST /api/v1/receipts/reprints/sync — up to 50 receipts a till printed
 * again, with one result per entry so the till can mark them sent.
 */
final class SyncReprints
{
    public function __invoke(SyncReprintsRequest $request, RecordReprint $recordReprint): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');
        $context = DeviceContext::of($device);

        /** @var list<array<string, mixed>> $reprints */
        $reprints = $request->validated('reprints');

        $results = array_map(fn (array $raw) => $this->syncOne($raw, $context, $recordReprint), $reprints);

        return response()->json(['results' => $results]);
    }

    /** @param array<string, mixed> $raw */
    private function syncOne(array $raw, DeviceContext $context, RecordReprint $recordReprint): SyncResult
    {
        $uuid = is_string($raw['uuid'] ?? null) ? $raw['uuid'] : null;
        $validator = Validator::make($raw, ReceiptReprintData::rules());
        $errors = $validator->errors()->toArray();

        foreach (array_diff(array_keys($raw), array_keys(ReceiptReprintData::rules())) as $key) {
            $errors[(string) $key] = ['Unknown field.'];
        }

        if ($errors !== []) {
            return SyncResult::rejected($uuid, 'invalid', $errors);
        }

        return $recordReprint->handle(ReceiptReprintData::from($validator->validated()), $context);
    }
}
