<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Actions;

use App\Domain\Catalogue\Actions\FindUnknownItems;
use App\Domain\Orders\Actions\FlagVoid;
use App\Domain\Orders\Actions\VoidOrder;
use App\Domain\Tenancy\Actions\FindUnknownStaff;
use App\Domain\Tenancy\Actions\NotifyTills;
use App\Domain\Tenancy\Actions\StaffMayAtBranch;
use App\Domain\Tenancy\Data\DeviceContext;
use App\Domain\Verticals\Data\TicketSyncResult;
use App\Domain\Verticals\Models\DiningTable;
use App\Domain\Verticals\Models\Ticket;
use App\Domain\Verticals\Models\TicketLine;
use App\Domain\Verticals\Models\TicketLineVoid;
use App\Infrastructure\Realtime\TillsShouldSync;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use VendaPOS\Core\Data\TicketData;
use VendaPOS\Core\Data\TicketLineData;
use VendaPOS\Core\Data\TicketLineVoidData;

/**
 * Saves one ticket a till sent (table service, T4b). Lines are only ever
 * added and keep the price they were sent at; resending a ticket, a line or a
 * void is a no-op. Taking a sent line off is a void, approved on the till with
 * a manager's PIN (T5c). Any till of the branch may add to any of its tickets,
 * so a waiter's order and the cashier's queue are one list.
 */
final class RecordTicket
{
    public function __construct(
        private readonly FindUnknownItems $findUnknownItems,
        private readonly FindUnknownStaff $findUnknownStaff,
        private readonly NotifyTills $notifyTills,
        private readonly StaffMayAtBranch $staffMayAtBranch,
        private readonly FlagVoid $flagVoid,
    ) {}

    public function handle(TicketData $data, DeviceContext $context): TicketSyncResult
    {
        $lines = $data->lineList();

        $voids = $data->voidList();

        $staffIds = array_values(array_unique([
            $data->staff_id,
            ...array_map(fn (TicketLineData $line) => $line->staff_id, $lines),
            ...array_merge(...array_map(fn (TicketLineVoidData $void) => [$void->voided_by, $void->approved_by], $voids)),
        ]));

        if ($this->findUnknownStaff->handle($staffIds) !== []) {
            return TicketSyncResult::rejected($data->uuid, 'unknown_staff');
        }

        $unknownItems = $this->findUnknownItems->handle(array_map(fn (TicketLineData $line) => $line->item_id, $lines));

        if ($unknownItems !== []) {
            return TicketSyncResult::rejected($data->uuid, 'unknown_items', ['item_ids' => $unknownItems]);
        }

        // A table switched off after the ticket was opened is still accepted: the guests are still there.
        if ($data->table_id !== null && ! DiningTable::query()->whereKey($data->table_id)->where('branch_id', $context->branchId)->exists()) {
            return TicketSyncResult::rejected($data->uuid, 'unknown_table');
        }

        try {
            $result = DB::transaction(fn () => $this->write($data, $context));
        } catch (UniqueConstraintViolationException) {
            // Another till created the same ticket or line at the same moment: once more, now it exists.
            $result = DB::transaction(fn () => $this->write($data, $context));
        }

        if ($result->status === TicketSyncResult::ACCEPTED || $result->status === TicketSyncResult::UPDATED) {
            $this->notifyTills->ofTenant($context->tenantId, TillsShouldSync::TICKETS, [$context->branchId]);
        }

        return $result;
    }

    private function write(TicketData $data, DeviceContext $context): TicketSyncResult
    {
        $ticket = Ticket::query()->whereKey($data->uuid)->lockForUpdate()->first();
        $isNew = $ticket === null;

        if ($ticket !== null && $ticket->branch_id !== $context->branchId) {
            return TicketSyncResult::rejected($data->uuid, 'uuid_conflict');
        }

        $ticket ??= Ticket::query()->create([
            'id' => $data->uuid,
            'branch_id' => $context->branchId,
            'device_id' => $context->deviceId,
            'staff_id' => $data->staff_id,
            'dining_table_id' => $data->table_id,
            'label' => $data->label !== null ? trim($data->label) : null,
            'status' => Ticket::OPEN,
            'opened_at' => Carbon::parse($data->opened_at)->utc(),
        ]);

        $lines = $data->lineList();
        $known = TicketLine::query()->whereKey(array_map(fn (TicketLineData $line) => $line->uuid, $lines))->pluck('ticket_id', 'id')->all();

        foreach ($known as $ticketId) {
            if ($ticketId !== $ticket->id) {
                return TicketSyncResult::rejected($data->uuid, 'line_conflict');
            }
        }

        $new = array_values(array_filter($lines, fn (TicketLineData $line) => ! array_key_exists($line->uuid, $known)));

        if ($isNew) {
            $this->applyEarlierPayment($ticket);
        }

        // Paid already: lines sent before the payment are kept as what it paid for; lines sent
        // after it were not paid, are refused, and the till must say so.
        $refused = [];

        if ($ticket->status === Ticket::CLOSED) {
            $paidAt = $ticket->closed_at;
            $refused = array_values(array_filter($new, fn (TicketLineData $line) => $paidAt === null || Carbon::parse($line->sent_at)->greaterThan($paidAt)));
            $new = array_values(array_filter($new, fn (TicketLineData $line) => ! in_array($line, $refused, true)));
        }

        if ($ticket->lines()->count() + count($new) > TicketData::MAX_LINES) {
            return TicketSyncResult::rejected($data->uuid, 'too_many_lines');
        }

        foreach ($new as $line) {
            $ticket->lines()->create([
                'id' => $line->uuid,
                'item_id' => $line->item_id,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unit_price_cents,
                'vat_category' => $line->vat_category,
                'line_total_cents' => $line->lineTotal()->cents,
                'staff_id' => $line->staff_id,
                'sent_at' => Carbon::parse($line->sent_at)->utc(),
            ]);
        }

        $voids = $this->recordVoids($ticket, $data->voidList(), $context);

        if ($voids === null) {
            return TicketSyncResult::rejected($data->uuid, 'void_conflict');
        }

        if ($ticket->status !== Ticket::CLOSED) {
            $this->applyBill($ticket, $data->bill_requested_at);
        }

        $changed = $isNew || $new !== [] || $voids['recorded'] > 0 || $ticket->isDirty(['status', 'bill_requested_at']);
        $ticket->save();

        return TicketSyncResult::saved(
            $ticket->id,
            $isNew ? TicketSyncResult::ACCEPTED : ($changed ? TicketSyncResult::UPDATED : TicketSyncResult::DUPLICATE),
            $ticket->status,
            array_map(fn (TicketLineData $line) => $line->uuid, $refused),
            $voids['refused'],
        );
    }

    /**
     * Takes voided quantities off the ticket's lines (T5c). A void stands
     * only while there is something left to take off and nobody has paid or
     * is paying: one that arrives after the payment is refused, unless the
     * same till made it before paying offline. Resending a void is a no-op;
     * a void UUID already on another ticket refuses the whole ticket (null).
     *
     * @param  list<TicketLineVoidData>  $voids
     * @return array{recorded: int, refused: list<string>}|null
     */
    private function recordVoids(Ticket $ticket, array $voids, DeviceContext $context): ?array
    {
        if ($voids === []) {
            return ['recorded' => 0, 'refused' => []];
        }

        $known = TicketLineVoid::query()->whereKey(array_map(fn (TicketLineVoidData $void) => $void->uuid, $voids))->pluck('ticket_id', 'id')->all();

        if (array_filter($known, fn (string $ticketId) => $ticketId !== $ticket->id) !== []) {
            return null;
        }

        $sent = $ticket->lines()->get(['id', 'name', 'quantity']);
        $lines = $sent->pluck('quantity', 'id')->all();
        $lineNames = $sent->pluck('name', 'id')->all();
        $voided = TicketLineVoid::query()->where('ticket_id', $ticket->id)->toBase()
            ->selectRaw('ticket_line_id, sum(quantity) as voided')->groupBy('ticket_line_id')
            ->pluck('voided', 'ticket_line_id')->map(fn (mixed $sum): int => (int) $sum)->all();
        $count = TicketLineVoid::query()->where('ticket_id', $ticket->id)->count();
        $beingPaidElsewhere = $ticket->held_for_order_id !== null && $ticket->held_by_device_id !== $context->deviceId && $ticket->held_until?->isFuture() === true;

        $recorded = 0;
        $refused = [];

        foreach ($voids as $void) {
            if (array_key_exists($void->uuid, $known)) {
                continue;
            }

            $voidedAt = Carbon::parse($void->voided_at)->utc();
            $left = ($lines[$void->line_uuid] ?? 0) - ($voided[$void->line_uuid] ?? 0);
            $afterPayment = $ticket->status === Ticket::CLOSED && ($ticket->closed_at === null || $voidedAt->greaterThan($ticket->closed_at));

            if ($left < $void->quantity || $afterPayment || $beingPaidElsewhere || $count >= TicketData::MAX_LINES) {
                $refused[] = $void->uuid;

                continue;
            }

            // Checked, never enforced: the kitchen was told when the till accepted the PIN.
            $approverAllowed = $this->staffMayAtBranch->handle($void->approved_by, VoidOrder::PERMISSION, $ticket->branch_id);

            TicketLineVoid::query()->create([
                'id' => $void->uuid,
                'ticket_id' => $ticket->id,
                'ticket_line_id' => $void->line_uuid,
                'device_id' => $context->deviceId,
                'quantity' => $void->quantity,
                'voided_at' => $voidedAt,
                'voided_by' => $void->voided_by,
                'approved_by' => $void->approved_by,
                'reason' => $void->reason,
                'note' => $void->note !== null ? trim($void->note) : null,
                'approver_allowed' => $approverAllowed,
            ]);

            if (! $approverAllowed) {
                $this->flagVoid->ticketLine($void->uuid, $ticket->id, $ticket->branch_id, $void->voided_by, $void->approved_by, $voidedAt, $lineNames[$void->line_uuid] ?? '', $void->quantity, $void->reason->value);
            }

            $voided[$void->line_uuid] = ($voided[$void->line_uuid] ?? 0) + $void->quantity;
            $recorded++;
            $count++;
        }

        if ($refused !== []) {
            Log::warning('tickets.voids_refused', ['ticket_id' => $ticket->id, 'voids' => $refused]);
        }

        return ['recorded' => $recorded, 'refused' => $refused];
    }

    /**
     * The bill stands only while nothing changed after it was printed: a
     * round ordered, or a line taken off, after the bill reopens the ticket.
     */
    private function applyBill(Ticket $ticket, ?string $billRequestedAt): void
    {
        $sent = $billRequestedAt !== null ? Carbon::parse($billRequestedAt)->utc() : null;

        if ($sent !== null && ($ticket->bill_requested_at === null || $sent->greaterThan($ticket->bill_requested_at))) {
            $ticket->bill_requested_at = $sent;
        }

        $lastChange = collect([$ticket->lines()->max('sent_at'), TicketLineVoid::query()->where('ticket_id', $ticket->id)->max('voided_at')])
            ->filter()
            ->map(fn (mixed $moment) => Carbon::parse((string) $moment))
            ->max();

        $ticket->status = $ticket->bill_requested_at !== null && ($lastChange === null || ! $lastChange->greaterThan($ticket->bill_requested_at))
            ? Ticket::BILL_REQUESTED
            : Ticket::OPEN;
    }

    /** The same till paid it before the ticket reached the server (offline). */
    private function applyEarlierPayment(Ticket $ticket): void
    {
        $closure = DB::table('ticket_closures')->where('ticket_id', $ticket->id)->where('branch_id', $ticket->branch_id)->first();

        if ($closure !== null) {
            $ticket->update(['status' => Ticket::CLOSED, 'order_id' => $closure->order_id, 'closed_at' => $closure->closed_at]);
        }
    }
}
