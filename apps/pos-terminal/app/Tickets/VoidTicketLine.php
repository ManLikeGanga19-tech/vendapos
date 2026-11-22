<?php

declare(strict_types=1);

namespace App\Tickets;

use App\Jobs\PushTicketsJob;
use App\Models\StaffMember;
use App\Models\Ticket;
use App\Models\TicketLine;
use App\Models\TicketLineVoid;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use VendaPOS\Core\Data\VoidData;
use VendaPOS\Core\Enums\VoidReason;

/**
 * Takes some or all of a sent line off an unpaid ticket, approved by a
 * manager's or owner's PIN (checked before this is called). Anyone who takes
 * orders may start it, a waiter included (Daniel, T5c). Written here first and
 * sent with the ticket, so it works offline; a printed bill is out of date
 * afterwards and the ticket goes back to open.
 */
final class VoidTicketLine
{
    /** @throws TicketRefused */
    public function handle(StaffMember $voider, StaffMember $approver, string $ticketId, string $lineId, int $quantity, VoidReason $reason, ?string $note): VoidedTicketLine
    {
        if (! $voider->can(StaffMember::TAKE_ORDERS)) {
            throw new TicketRefused('not_allowed');
        }

        if (! $approver->can(StaffMember::VOID_ORDERS)) {
            throw new TicketRefused('approver_not_allowed');
        }

        $note = $note === null || trim($note) === '' ? null : trim($note);

        if (! in_array($reason, VoidReason::forTicketLines(), true) || ($reason->needsNote() && $note === null) || mb_strlen((string) $note) > VoidData::NOTE_MAX) {
            throw new TicketRefused('note_needed');
        }

        $ticket = Ticket::query()->unpaid()->with('lines')->find($ticketId) ?? throw new TicketRefused('paid');
        $line = $ticket->lines->firstWhere('id', $lineId);

        if (! $line instanceof TicketLine || $line->sync_status === TicketLine::REFUSED) {
            throw new TicketRefused('unknown_line');
        }

        if ($quantity < 1 || $quantity > $line->remainingQuantity()) {
            throw new TicketRefused('too_many');
        }

        $voidedAt = CarbonImmutable::now()->utc();

        DB::transaction(function () use ($ticket, $line, $quantity, $voidedAt, $voider, $approver, $reason, $note): void {
            TicketLineVoid::query()->create([
                'id' => (string) Str::uuid7(),
                'ticket_id' => $ticket->id,
                'ticket_line_id' => $line->id,
                'quantity' => $quantity,
                'voided_at' => $voidedAt,
                'voided_by' => $voider->id,
                'approved_by' => $approver->id,
                'reason' => $reason->value,
                'note' => $note,
                'sync_status' => TicketLineVoid::PENDING,
            ]);

            // The bill printed before this no longer matches: print a fresh one.
            $ticket->update(['status' => Ticket::OPEN, 'sync_status' => Ticket::PENDING, 'sync_reason' => null]);
        });

        PushTicketsJob::dispatch();

        return new VoidedTicketLine(
            ticketId: $ticket->id,
            ticketTitle: $ticket->title(),
            name: $line->name,
            quantity: $quantity,
            left: $line->remainingQuantity() - $quantity,
            voidedAt: $voidedAt,
            reason: $reason->value,
            note: $note,
            voidedBy: $voider->name,
            approvedBy: $approver->name,
        );
    }
}
