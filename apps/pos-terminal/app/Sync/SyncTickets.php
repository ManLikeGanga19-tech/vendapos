<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\VendaApi;
use App\Models\Ticket;
use App\Models\TicketLine;
use App\Models\TicketLineVoid;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps this till's tickets and the branch's in step (table service). Sends
 * what this till changed first, in batches of up to 50, then copies the
 * branch's unpaid tickets from the server. A ticket this till changed and
 * the server does not have yet is never overwritten or dropped.
 */
final class SyncTickets
{
    public const int BATCH = 50;

    /** Keeps one request well under the server's 64 KB body limit. */
    private const int MAX_LINES_PER_REQUEST = 150;

    private const int MAX_BATCHES = 10;

    /** The server refused lines sent after the ticket was paid. */
    public const string LINES_AFTER_PAYMENT = 'lines_after_payment';

    public function __construct(private readonly VendaApi $api) {}

    public function handle(): void
    {
        $this->push();
        $this->pull();
    }

    /** Sends this till's changes. */
    public function push(): void
    {
        $tried = [];

        for ($batch = 0; $batch < self::MAX_BATCHES; $batch++) {
            // Each ticket once per round: one the server did not answer waits for the next round.
            $tickets = $this->nextBatch($tried);

            if ($tickets === []) {
                return;
            }

            $tried = [...$tried, ...array_map(fn (Ticket $ticket) => $ticket->id, $tickets)];

            $results = collect($this->api->syncTickets(array_map(fn (Ticket $ticket) => $ticket->toSyncPayload(), $tickets)))
                ->filter(fn (array $result) => $result['uuid'] !== null)
                ->keyBy('uuid');

            foreach ($tickets as $ticket) {
                $result = $results->get($ticket->id);

                if ($result !== null) {
                    $this->apply($ticket, $result);
                }
            }
        }
    }

    /** Copies the branch's unpaid tickets; one missing from the server's list was paid. */
    public function pull(): void
    {
        $server = $this->api->tickets();

        DB::transaction(function () use ($server): void {
            $ids = array_column($server, 'id');

            $gone = Ticket::query()->where('sync_status', Ticket::SYNCED)->whereNotIn('id', $ids)->pluck('id')->all();
            TicketLineVoid::query()->whereIn('ticket_id', $gone)->delete();
            TicketLine::query()->whereIn('ticket_id', $gone)->delete();
            Ticket::query()->whereKey($gone)->delete();

            foreach ($server as $row) {
                $local = Ticket::query()->find($row['id']);

                if ($local === null) {
                    $local = Ticket::query()->create([
                        'id' => $row['id'],
                        'table_id' => $row['table_id'],
                        'table_name' => $row['table_name'],
                        'label' => $row['label'],
                        'staff_id' => $row['staff_id'],
                        'status' => $row['status'],
                        'opened_at' => Carbon::parse($row['opened_at'])->utc(),
                        'bill_requested_at' => $row['bill_requested_at'] !== null ? Carbon::parse($row['bill_requested_at'])->utc() : null,
                        'sync_status' => Ticket::SYNCED,
                        'on_server' => true,
                    ]);
                } elseif ($local->sync_status === Ticket::SYNCED && $local->status !== Ticket::CLOSED) {
                    // Paid here but not yet on the server: it stays closed on this till.
                    $local->update([
                        'status' => $row['status'],
                        'table_name' => $row['table_name'],
                        'bill_requested_at' => $row['bill_requested_at'] !== null ? Carbon::parse($row['bill_requested_at'])->utc() : null,
                    ]);
                }

                $known = $local->lines()->pluck('id')->all();

                foreach ($row['lines'] as $line) {
                    if (in_array($line['id'] ?? null, $known, true)) {
                        continue;
                    }

                    TicketLine::query()->create([
                        'id' => (string) $line['id'],
                        'ticket_id' => $local->id,
                        'item_id' => (string) $line['item_id'],
                        'name' => (string) $line['name'],
                        'quantity' => (int) $line['quantity'],
                        'unit_price_cents' => (int) $line['unit_price_cents'],
                        'vat_category' => (string) $line['vat_category'],
                        'staff_id' => (string) $line['staff_id'],
                        'sent_at' => Carbon::parse((string) $line['sent_at'])->utc(),
                        'sync_status' => TicketLine::SYNCED,
                    ]);
                }

                // What a manager took off on any till; this till's own are already here.
                $knownVoids = $local->voids()->pluck('id')->all();

                foreach ($row['voids'] as $void) {
                    if (in_array($void['id'] ?? null, $knownVoids, true)) {
                        continue;
                    }

                    TicketLineVoid::query()->create([
                        'id' => (string) $void['id'],
                        'ticket_id' => $local->id,
                        'ticket_line_id' => (string) $void['line_id'],
                        'quantity' => (int) $void['quantity'],
                        'voided_at' => Carbon::parse((string) $void['voided_at'])->utc(),
                        'voided_by' => (string) $void['voided_by'],
                        'approved_by' => (string) $void['approved_by'],
                        'reason' => (string) $void['reason'],
                        'note' => isset($void['note']) ? (string) $void['note'] : null,
                        'sync_status' => TicketLineVoid::SYNCED,
                    ]);
                }
            }
        });
    }

    /**
     * Pending tickets, oldest first, up to 50 and up to the line budget (at
     * least one ticket, so a big one still goes).
     *
     * @param  list<string>  $skip
     * @return list<Ticket>
     */
    private function nextBatch(array $skip): array
    {
        $tickets = [];
        $lines = 0;

        foreach (Ticket::query()->where('sync_status', Ticket::PENDING)->whereNotIn('id', $skip)->with(['lines', 'voids'])->orderBy('opened_at')->limit(self::BATCH)->get() as $ticket) {
            // A void weighs about as much as a line in the request.
            $pending = $ticket->lines->where('sync_status', TicketLine::PENDING)->count() + $ticket->voids->where('sync_status', TicketLineVoid::PENDING)->count();

            if ($tickets !== [] && $lines + $pending > self::MAX_LINES_PER_REQUEST) {
                break;
            }

            $tickets[] = $ticket;
            $lines += $pending;
        }

        return $tickets;
    }

    /** @param  array{uuid: string|null, status: string, ticket_status: string|null, refused_lines: list<string>, refused_voids: list<string>, reason: string|null}  $result */
    private function apply(Ticket $ticket, array $result): void
    {
        if ($result['status'] === 'rejected') {
            $ticket->update(['sync_status' => Ticket::REJECTED, 'sync_reason' => $result['reason'] ?? 'rejected']);
            Log::warning('till.ticket_rejected', ['ticket_id' => $ticket->id, 'reason' => $result['reason']]);

            return;
        }

        DB::transaction(function () use ($ticket, $result): void {
            $ticket->update(['on_server' => true]);
            $ticket->lines()->where('sync_status', TicketLine::PENDING)->whereIn('id', $result['refused_lines'])->update(['sync_status' => TicketLine::REFUSED]);
            $ticket->lines()->where('sync_status', TicketLine::PENDING)->update(['sync_status' => TicketLine::SYNCED]);

            // Not taken off after all (paid, or being paid on another till): the line counts in full again.
            $ticket->voids()->where('sync_status', TicketLineVoid::PENDING)->whereIn('id', $result['refused_voids'])->update(['sync_status' => TicketLineVoid::REFUSED]);
            $ticket->voids()->where('sync_status', TicketLineVoid::PENDING)->update(['sync_status' => TicketLineVoid::SYNCED]);

            if ($result['refused_voids'] !== []) {
                Log::warning('till.ticket_voids_refused', ['ticket_id' => $ticket->id, 'voids' => $result['refused_voids']]);
            }

            // Lines sent after it was paid: kept in view until a manager has dealt with them.
            if ($result['refused_lines'] !== []) {
                $ticket->update(['status' => Ticket::CLOSED, 'sync_status' => Ticket::REJECTED, 'sync_reason' => self::LINES_AFTER_PAYMENT]);
                Log::warning('till.ticket_lines_after_payment', ['ticket_id' => $ticket->id, 'lines' => $result['refused_lines']]);

                return;
            }

            $status = $ticket->status === Ticket::CLOSED ? Ticket::CLOSED : ($result['ticket_status'] ?? $ticket->status);
            $ticket->update(['status' => $status, 'sync_status' => Ticket::SYNCED, 'sync_reason' => null]);
        });
    }
}
