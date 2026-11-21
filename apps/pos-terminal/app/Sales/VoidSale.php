<?php

declare(strict_types=1);

namespace App\Sales;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\VendaApi;
use App\CashUp\Drawer;
use App\Jobs\PushOrdersJob;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Shift;
use App\Models\StaffMember;
use App\Security\KeyringUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use VendaPOS\Core\Data\VoidData;
use VendaPOS\Core\Enums\CashMovementKind;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\VoidReason;

/**
 * Voids a whole completed sale of today, approved by a manager's or owner's
 * PIN (checked before this is called). A sale this till made is voided in its
 * own database first and synced like a sale, so it works offline; a sale of
 * another till of the branch is voided on the server, online only. Cash
 * given back comes out of this till's open shift, recorded there as a void
 * refund; a cash sale cannot be voided on a till with no shift open.
 */
final class VoidSale
{
    public function __construct(
        private readonly SalesToday $salesToday,
        private readonly VendaApi $api,
        private readonly Drawer $drawer,
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

        $cashBack = $sale->cashCents();
        $shift = $cashBack > 0 ? ($this->drawer->current() ?? throw new VoidRefused('no_shift')) : null;

        $voidedAt = CarbonImmutable::now()->utc();
        $order = Order::query()->find($saleId);

        if ($order instanceof Order) {
            DB::transaction(function () use ($order, $voidedAt, $voider, $approver, $reason, $note, $shift, $cashBack) {
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
                $this->refund($shift, $cashBack, $voider, $order->id, $voidedAt);
            });
        } else {
            $this->voidOnServer($saleId, [
                'voided_at' => $voidedAt->toIso8601String(),
                'voided_by' => $voider->id,
                'approved_by' => $approver->id,
                'reason' => $reason->value,
                'note' => $note,
            ]);
            $this->refund($shift, $cashBack, $voider, $saleId, $voidedAt);
        }

        PushOrdersJob::dispatch();
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
     * The cash handed back leaves this drawer. No approver on the movement:
     * the void carries whose PIN approved it.
     */
    private function refund(?Shift $shift, int $cashBack, StaffMember $voider, string $saleId, CarbonImmutable $at): void
    {
        if ($shift === null || $cashBack <= 0) {
            return;
        }

        CashMovement::query()->create([
            'id' => (string) Str::uuid7(),
            'shift_id' => $shift->id,
            'kind' => CashMovementKind::VoidRefund,
            'amount_cents' => $cashBack,
            'moved_at' => $at,
            'staff_id' => $voider->id,
            'order_id' => $saleId,
            'synced' => false,
        ]);
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
