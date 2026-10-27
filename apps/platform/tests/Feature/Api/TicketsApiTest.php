<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Verticals\Actions\ManageFloor;
use App\Domain\Verticals\Models\DiningTable;
use App\Domain\Verticals\Models\Ticket;
use App\Domain\Verticals\Models\TicketLine;
use App\Infrastructure\Realtime\TillsShouldSync;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    [$this->tenant, $this->branch] = tenantWithBranch(BusinessType::Restaurant);
    $this->enrolment = enrolFor($this->tenant, $this->branch);

    $this->tenant->run(function () {
        $this->tusker = Item::create(['type' => ItemType::Product, 'name' => 'Tusker', 'price_cents' => 35_000, 'vat_category' => VatCategory::Standard]);
        $this->water = Item::create(['type' => ItemType::Product, 'name' => 'Water 500 ml', 'price_cents' => 5_000, 'vat_category' => VatCategory::ZeroRated]);
        $floor = app(ManageFloor::class);
        [$this->table4] = $floor->addTables($floor->saveArea(null, $this->branch->id, 'Main hall')->id, '4', 4);
    });
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** @return array<string, mixed> */
function ticketLine(Item $item, int $quantity = 1, string $sentAt = '2026-10-27T13:06:00+03:00', array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'item_id' => $item->id,
        'name' => $item->name,
        'quantity' => $quantity,
        'unit_price_cents' => $item->price_cents,
        'vat_category' => $item->vat_category->value,
        'staff_id' => sellerFor(saleTenant()),
        'sent_at' => $sentAt,
    ], $overrides);
}

/** @return array<string, mixed> */
function ticketOf(array $lines, array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'staff_id' => sellerFor(saleTenant()),
        'opened_at' => '2026-10-27T13:05:00+03:00',
        'table_id' => test()->table4->id,
        'label' => null,
        'lines' => $lines,
    ], $overrides);
}

function syncTickets(array $tickets, ?DeviceEnrolment $as = null): TestResponse
{
    $as ??= test()->enrolment;
    app('auth')->forgetGuards();

    return test()->withHeaders([
        'Authorization' => 'Bearer '.$as->plainTextToken,
        'X-Device-ID' => $as->device->id,
    ])->postJson('/api/v1/tickets/sync', ['tickets' => $tickets]);
}

function openTickets(?DeviceEnrolment $as = null): TestResponse
{
    $as ??= test()->enrolment;

    return deviceRequest('GET', '/api/v1/tickets', $as->plainTextToken, $as->device->id);
}

it('saves a waiter’s ticket in the till’s branch and tells that branch’s tills', function () {
    Event::fake([TillsShouldSync::class]);
    $ticket = ticketOf([ticketLine($this->tusker, 3), ticketLine($this->water, 2)]);

    syncTickets([$ticket])->assertOk()->assertExactJson(['results' => [
        ['uuid' => $ticket['uuid'], 'status' => 'accepted', 'ticket_status' => 'open'],
    ]]);

    openTickets()->assertOk()
        ->assertJsonCount(1, 'tickets')
        ->assertJsonPath('tickets.0.id', $ticket['uuid'])
        ->assertJsonPath('tickets.0.table_name', '4')
        ->assertJsonPath('tickets.0.total_cents', 115_000)
        ->assertJsonPath('tickets.0.lines.*.name', ['Tusker', 'Water 500 ml'])
        ->assertJsonPath('tickets.0.opened_at', '2026-10-27T10:05:00+00:00');

    expect($this->tenant->run(fn () => Ticket::query()->sole()->branch_id))->toBe($this->branch->id);
    Event::assertDispatched(TillsShouldSync::class, fn (TillsShouldSync $e) => $e->what === TillsShouldSync::TICKETS && $e->deviceIds === [$this->enrolment->device->id]);
});

it('is idempotent, only ever adds lines, and keeps the price a line was sent at', function () {
    $first = ticketLine($this->tusker, 2);
    $ticket = ticketOf([$first]);
    syncTickets([$ticket])->assertOk();

    syncTickets([$ticket])->assertJsonPath('results.0.status', 'duplicate');

    // A resend with a new price, and a line the till no longer sends: neither changes what was sent.
    $second = ticketLine($this->water, 1, '2026-10-27T13:20:00+03:00');
    $first['unit_price_cents'] = 1;
    syncTickets([ticketOf([$first, $second], ['uuid' => $ticket['uuid']])])->assertJsonPath('results.0.status', 'updated');
    syncTickets([ticketOf([$second], ['uuid' => $ticket['uuid']])])->assertJsonPath('results.0.status', 'duplicate');

    expect($this->tenant->run(fn () => TicketLine::query()->orderBy('sent_at')->pluck('unit_price_cents')->all()))->toBe([35_000, 5_000]);
    openTickets()->assertJsonPath('tickets.0.total_cents', 75_000);
});

it('lets any till of the branch add to a ticket, so waiters and the cashier share one list', function () {
    $ticket = ticketOf([ticketLine($this->tusker)]);
    syncTickets([$ticket])->assertOk();

    $cashier = enrolFor($this->tenant, $this->branch);
    syncTickets([ticketOf([ticketLine($this->water)], ['uuid' => $ticket['uuid']])], $cashier)->assertJsonPath('results.0.status', 'updated');

    openTickets($cashier)->assertJsonPath('tickets.0.lines.*.name', ['Tusker', 'Water 500 ml']);
});

it('marks the bill printed, and reopens the ticket when another round is sent after it', function () {
    $ticket = ticketOf([ticketLine($this->tusker)], ['bill_requested_at' => '2026-10-27T13:30:00+03:00']);
    syncTickets([$ticket])->assertJsonPath('results.0.ticket_status', 'bill_requested');
    openTickets()->assertJsonPath('tickets.0.bill_requested_at', '2026-10-27T10:30:00+00:00');

    syncTickets([ticketOf([ticketLine($this->water, 1, '2026-10-27T13:35:00+03:00')], ['uuid' => $ticket['uuid'], 'bill_requested_at' => '2026-10-27T13:30:00+03:00'])])
        ->assertJsonPath('results.0.ticket_status', 'open');

    syncTickets([ticketOf([], ['uuid' => $ticket['uuid'], 'bill_requested_at' => '2026-10-27T13:40:00+03:00'])])
        ->assertJsonPath('results.0.ticket_status', 'bill_requested');
});

it('takes takeaway and counter tickets with no table', function () {
    $ticket = ticketOf([ticketLine($this->water)], ['table_id' => null, 'label' => '  Otieno ']);

    syncTickets([$ticket])->assertJsonPath('results.0.status', 'accepted');
    openTickets()->assertJsonPath('tickets.0.table_id', null)->assertJsonPath('tickets.0.label', 'Otieno');
});

it('closes the ticket when its sale syncs, and refuses lines sent after it was paid', function () {
    $ticket = ticketOf([ticketLine($this->tusker, 2, '2026-10-27T13:06:00+03:00')]);
    syncTickets([$ticket])->assertOk();

    $sale = saleOf([[$this->tusker, 2]], overrides: ['ticket_uuid' => $ticket['uuid'], 'placed_at' => '2026-10-27T13:50:00+03:00']);
    sync([$sale])->assertJsonPath('results.0.status', 'accepted');

    openTickets()->assertJsonCount(0, 'tickets');
    expect($this->tenant->run(fn () => Ticket::query()->sole()->only(['status', 'order_id'])))->toBe(['status' => 'closed', 'order_id' => $sale['uuid']]);

    $late = ticketLine($this->water, 1, '2026-10-27T13:55:00+03:00');
    syncTickets([ticketOf([$late], ['uuid' => $ticket['uuid']])])
        ->assertJsonPath('results.0.ticket_status', 'closed')
        ->assertJsonPath('results.0.refused_lines', [$late['uuid']]);

    expect($this->tenant->run(fn () => TicketLine::query()->count()))->toBe(1);
});

it('closes a ticket that reaches the server after the sale that paid it, keeping the lines it paid for', function () {
    $ticket = ticketOf([ticketLine($this->tusker, 1, '2026-10-27T13:06:00+03:00')]);
    sync([saleOf([[$this->tusker, 1]], overrides: ['ticket_uuid' => $ticket['uuid'], 'placed_at' => '2026-10-27T13:50:00+03:00'])])->assertOk();

    syncTickets([$ticket])
        ->assertJsonPath('results.0.status', 'accepted')
        ->assertJsonPath('results.0.ticket_status', 'closed')
        ->assertJsonMissingPath('results.0.refused_lines');

    openTickets()->assertJsonCount(0, 'tickets');
    expect($this->tenant->run(fn () => TicketLine::query()->count()))->toBe(1);
});

it('leaves the ticket open when its sale was cancelled', function () {
    $ticket = ticketOf([ticketLine($this->tusker)]);
    syncTickets([$ticket])->assertOk();

    sync([saleOf([[$this->tusker, 1]], overrides: ['ticket_uuid' => $ticket['uuid'], 'status' => 'cancelled'])])->assertOk();

    openTickets()->assertJsonCount(1, 'tickets');
});

it('refuses another branch’s table, unknown items, unknown staff and unknown fields', function () {
    $other = $this->tenant->run(function () {
        $bar = Branch::create(['name' => 'Bar', 'business_type' => BusinessType::Bar]);
        $floor = app(ManageFloor::class);

        return $floor->addTables($floor->saveArea(null, $bar->id, 'Counter')->id, 'B1', 1)[0];
    });

    $results = syncTickets([
        ticketOf([ticketLine($this->tusker)], ['table_id' => $other->id]),
        ticketOf([ticketLine($this->tusker, overrides: ['item_id' => (string) Str::uuid()])]),
        ticketOf([ticketLine($this->tusker)], ['staff_id' => (string) Str::uuid()]),
        ticketOf([ticketLine($this->tusker, overrides: ['line_total_cents' => 1])], ['branch_id' => $other->branch_id]),
    ])->assertOk()->json('results');

    expect(array_column($results, 'reason'))->toBe(['unknown_table', 'unknown_items', 'unknown_staff', 'invalid'])
        ->and(array_keys($results[3]['errors']))->toEqualCanonicalizing(['branch_id', 'lines.0.line_total_cents']);
    expect($this->tenant->run(fn () => Ticket::query()->count()))->toBe(0);
});

it('never shows or changes a ticket across branches or businesses', function () {
    $ticket = ticketOf([ticketLine($this->tusker)]);
    syncTickets([$ticket])->assertOk();

    // Another branch of the same business: the same UUID is refused, and its list is empty.
    $bar = $this->tenant->run(fn () => Branch::create(['name' => 'Bar', 'business_type' => BusinessType::Bar]));
    $barTill = enrolFor($this->tenant, $bar);
    syncTickets([ticketOf([ticketLine($this->water)], ['uuid' => $ticket['uuid'], 'table_id' => null])], $barTill)->assertJsonPath('results.0.reason', 'uuid_conflict');
    openTickets($barTill)->assertJsonCount(0, 'tickets');

    // Another business: its own schema, its own list; nothing of ours is visible or touched.
    [$otherTenant, $otherBranch] = tenantWithBranch(BusinessType::Restaurant);
    $theirTill = enrolFor($otherTenant, $otherBranch);
    openTickets($theirTill)->assertJsonCount(0, 'tickets');

    $theirWater = $otherTenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Water', 'price_cents' => 5_000, 'vat_category' => VatCategory::ZeroRated]));
    $theirSeller = sellerFor($otherTenant);
    syncTickets([ticketOf([ticketLine($theirWater, overrides: ['staff_id' => $theirSeller])], ['uuid' => $ticket['uuid'], 'table_id' => null, 'staff_id' => $theirSeller])], $theirTill)
        ->assertJsonPath('results.0.status', 'accepted');

    expect($this->tenant->run(fn () => TicketLine::query()->pluck('name')->all()))->toBe(['Tusker'])
        ->and($otherTenant->run(fn () => TicketLine::query()->pluck('name')->all()))->toBe(['Water']);
    openTickets()->assertJsonPath('tickets.0.lines.*.name', ['Tusker']);
});

it('refuses a batch over 50 tickets and an unknown top-level key', function () {
    syncTickets(array_fill(0, 51, ticketOf([])))->assertUnprocessable()->assertJsonValidationErrors('tickets');

    app('auth')->forgetGuards();
    $this->withHeaders([
        'Authorization' => 'Bearer '.$this->enrolment->plainTextToken,
        'X-Device-ID' => $this->enrolment->device->id,
    ])->postJson('/api/v1/tickets/sync', ['tickets' => [ticketOf([])], 'branch_id' => (string) Str::uuid()])
        ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
});

it('keeps a table switched off after the ticket was opened: the guests are still seated', function () {
    $this->tenant->run(fn () => app(ManageFloor::class)->setTableActive($this->table4->id, false));

    syncTickets([ticketOf([ticketLine($this->tusker)])])->assertJsonPath('results.0.status', 'accepted');
    expect($this->tenant->run(fn () => DiningTable::query()->find($this->table4->id)?->is_active))->toBeFalse();
});
