<?php

declare(strict_types=1);

namespace App\Sales;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\VendaApi;
use App\Jobs\PushOrdersJob;
use App\Models\Order;
use App\Models\StaffMember;
use App\Security\KeyringUnavailable;
use Carbon\CarbonImmutable;
use VendaPOS\Core\Data\VoidData;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\VoidReason;

/**
 * Voids a whole completed sale of today, approved by a manager's or owner's
 * PIN (checked before this is called). A sale this till made is voided in its
 * own database first and synced like a sale, so it works offline; a sale of
 * another till of the branch is voided on the server, online only.
 */
final class VoidSale
{
    public function __construct(
        private readonly SalesToday $salesToday,
        private readonly VendaApi $api,
    ) {}

    /** @throws VoidRefused */
    public function handle(StaffMember $voider, StaffMember $approver, string $saleId, VoidReason $reason, ?string $note): VoidedSale
    {
        if (! $voider->can(StaffMember::TAKE_PAYMENTS)) {
            throw new VoidRefused('not_allowed');
        }

        if (! $approver->can(StaffMember::VOID_ORDERS)) {
            throw new VoidRefused('approver_not_allowed');
        }

        $note = $note === null || trim($note) === '' ? null : trim($note);

        if (($reason->needsNote() && $note === null) || mb_strlen((string) $note) > VoidData::NOTE_MAX) {
            throw new VoidRefused('note_needed');
        }

        // Today's sales only (Africa/Nairobi): an older sale is a refund, not a void.
        $sale = $this->salesToday->find($saleId) ?? throw new VoidRefused('not_found');

        if ($sale->isVoided()) {
            throw new VoidRefused('already_voided');
        }

        $voidedAt = CarbonImmutable::now()->utc();
        $order = Order::query()->find($saleId);

        if ($order instanceof Order) {
            $order->update([
                'status' => OrderStatus::Voided->value,
                'voided_at' => $voidedAt,
                'voided_by' => $voider->id,
                'approved_by' => $approver->id,
                'void_reason' => $reason->value,
                'void_note' => $note,
                // Sent again: the server records the void when it arrives.
                'sync_status' => Order::PENDING,
                'sync_reason' => null,
                'sync_errors' => null,
            ]);
            PushOrdersJob::dispatch();
        } else {
            $this->voidOnServer($saleId, [
                'voided_at' => $voidedAt->toIso8601String(),
                'voided_by' => $voider->id,
                'approved_by' => $approver->id,
                'reason' => $reason->value,
                'note' => $note,
            ]);
        }

        $this->salesToday->forget();

        return new VoidedSale(
            receiptNumber: $sale->receiptNumber,
            placedAt: $sale->placedAt,
            voidedAt: $voidedAt,
            lines: $sale->lines,
            totalCents: $sale->totalCents,
            cashCents: $sale->cashCents(),
            mpesaCents: $sale->mpesaCents(),
            reason: $reason->value,
            note: $note,
            voidedBy: $voider->name,
            approvedBy: $approver->name,
        );
    }

    /**
     * @param  array{voided_at: string, voided_by: string, approved_by: string, reason: string, note: ?string}  $void
     *
     * @throws VoidRefused
     */
    private function voidOnServer(string $saleId, array $void): void
    {
        try {
            $outcome = $this->api->voidOrder($saleId, $void);
        } catch (ApiUnavailable|TillNotAuthorised|ApiNotConfigured|KeyringUnavailable) {
            throw new VoidRefused('offline');
        }

        match ($outcome) {
            'voided' => null,
            'not_voidable' => throw new VoidRefused('already_voided'),
            'not_found' => throw new VoidRefused('not_found'),
            default => throw new VoidRefused('refused'),
        };
    }
}
