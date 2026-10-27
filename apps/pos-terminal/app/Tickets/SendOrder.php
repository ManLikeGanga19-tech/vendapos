<?php

declare(strict_types=1);

namespace App\Tickets;

use App\Jobs\PushTicketsJob;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\StaffMember;
use App\Models\Ticket;
use App\Models\TicketLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A waiter sends what they took to a ticket: a new one at a table or for
 * takeaway, or one already open. Written to this till first (CLAUDE.md §3.5),
 * priced from the menu now and locked at that price (Daniel, T4b). The push to
 * the server follows, and works offline later.
 */
final class SendOrder
{
    /**
     * @param  list<array{item_id: string, quantity: int}>  $cart
     *
     * @throws TicketRefused
     */
    public function handle(StaffMember $staff, array $cart, ?Ticket $ticket = null, ?string $tableId = null, ?string $label = null): Ticket
    {
        if (! $staff->can(StaffMember::TAKE_ORDERS)) {
            throw new TicketRefused('not_allowed');
        }

        if ($cart === []) {
            throw new TicketRefused('empty');
        }

        if ($ticket !== null && $ticket->status === Ticket::CLOSED) {
            throw new TicketRefused('paid');
        }

        $table = $tableId !== null ? DiningTable::query()->find($tableId) : null;

        if ($ticket === null && $tableId !== null && $table === null) {
            throw new TicketRefused('unknown_table');
        }

        $items = Item::query()->where('is_active', true)->whereKey(array_column($cart, 'item_id'))->get()->keyBy('id');

        foreach ($cart as $entry) {
            if (! $items->has($entry['item_id']) || $entry['quantity'] < 1) {
                throw new TicketRefused('unknown_item');
            }
        }

        $ticket = DB::transaction(function () use ($staff, $cart, $ticket, $table, $label, $items): Ticket {
            $ticket ??= Ticket::query()->create([
                'id' => (string) Str::uuid7(),
                'table_id' => $table?->id,
                'table_name' => $table?->name,
                'label' => $label !== null && trim($label) !== '' ? mb_substr(trim($label), 0, 40) : null,
                'staff_id' => $staff->id,
                'status' => Ticket::OPEN,
                'opened_at' => now()->utc(),
            ]);

            $sentAt = now()->utc();

            foreach ($cart as $entry) {
                /** @var Item $item */
                $item = $items->get($entry['item_id']);

                $ticket->lines()->create([
                    'id' => (string) Str::uuid7(),
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $entry['quantity'],
                    'unit_price_cents' => $item->price_cents,
                    'vat_category' => $item->vat_category,
                    'staff_id' => $staff->id,
                    'sent_at' => $sentAt,
                    'sync_status' => TicketLine::PENDING,
                ]);
            }

            // A round after the bill: the printed bill is out of date.
            $ticket->update(['status' => Ticket::OPEN, 'sync_status' => Ticket::PENDING, 'sync_reason' => null]);

            return $ticket;
        });

        PushTicketsJob::dispatch();

        return $ticket->load('lines');
    }
}
