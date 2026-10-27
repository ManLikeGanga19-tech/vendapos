<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Data\DeviceContext;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Verticals\Actions\RecordTicket;
use App\Domain\Verticals\Data\TicketSyncResult;
use App\Http\Api\V1\Requests\SyncTicketsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\TicketData;
use VendaPOS\Core\Data\TicketLineData;

/**
 * POST /api/v1/tickets/sync — up to 50 tickets a till opened or added to. The
 * reply lists one result per ticket, so the till can mark its sent lines off.
 */
final class SyncTickets
{
    public function __invoke(SyncTicketsRequest $request, RecordTicket $recordTicket): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');
        $context = new DeviceContext($device->tenant_id, $device->branch_id, $device->id);

        /** @var list<array<string, mixed>> $tickets */
        $tickets = $request->validated('tickets');

        $results = array_map(fn (array $raw) => $this->syncOne($raw, $context, $recordTicket), $tickets);

        return response()->json(['results' => $results]);
    }

    /** @param array<string, mixed> $raw */
    private function syncOne(array $raw, DeviceContext $context, RecordTicket $recordTicket): TicketSyncResult
    {
        $uuid = is_string($raw['uuid'] ?? null) ? $raw['uuid'] : null;
        $validator = Validator::make($raw, TicketData::validationRules());
        $errors = $validator->errors()->toArray();

        // Unknown keys are rejected at both levels (branch_id in the body included).
        foreach (array_diff(array_keys($raw), array_keys(TicketData::rules())) as $key) {
            $errors[(string) $key] = ['Unknown field.'];
        }

        foreach (is_array($raw['lines'] ?? null) ? $raw['lines'] : [] as $index => $line) {
            foreach (array_diff(array_keys(is_array($line) ? $line : []), array_keys(TicketLineData::rules())) as $key) {
                $errors["lines.{$index}.{$key}"] = ['Unknown field.'];
            }
        }

        if ($errors !== []) {
            return TicketSyncResult::rejected($uuid, 'invalid', $errors);
        }

        return $recordTicket->handle(TicketData::from($validator->validated()), $context);
    }
}
