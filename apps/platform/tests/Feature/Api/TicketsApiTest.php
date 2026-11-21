<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\MpesaCheck;
use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Verticals\Actions\ManageFloor;
use App\Domain\Verticals\Models\DiningTable;
use App\Domain\Verticals\Models\Ticket;
use App\Domain\Verticals\Models\TicketLine;
use App\Domain\Verticals\Models\TicketLineVoid;
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
    $bar = $this->tenant->run(fn () => Branch::create(['name' => 'Upstairs', 'business_type' => BusinessType::Restaurant]));
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

it('keeps tickets to tills of a branch that runs table service', function () {
    $duka = $this->tenant->run(fn () => Branch::create(['name' => 'Duka', 'business_type' => BusinessType::Shop]));
    $shopTill = enrolFor($this->tenant, $duka);
    $ticket = ticketOf([ticketLine($this->tusker)]);
    $ticketId = (string) $ticket['uuid'];

    $refused = fn (TestResponse $response) => $response->assertForbidden()->assertJsonPath('code', 'not_table_service');

    $refused(openTickets($shopTill));
    $refused(syncTickets([$ticket], $shopTill));
    $refused(deviceRequest('POST', "/api/v1/tickets/{$ticketId}/hold", $shopTill->plainTextToken, $shopTill->device->id));
    $refused(deviceRequest('DELETE', "/api/v1/tickets/{$ticketId}/hold", $shopTill->plainTextToken, $shopTill->device->id));

    expect($this->tenant->run(fn () => Ticket::query()->count()))->toBe(0);
    openTickets()->assertOk();
});

describe('paid once', function () {
    function holdTicket(string $ticketId, string $saleId, ?DeviceEnrolment $as = null, string $method = 'POST'): TestResponse
    {
        $as ??= test()->enrolment;
        app('auth')->forgetGuards();

        return test()->withHeaders([
            'Authorization' => 'Bearer '.$as->plainTextToken,
            'X-Device-ID' => $as->device->id,
        ])->json($method, "/api/v1/tickets/{$ticketId}/hold", ['sale_uuid' => $saleId]);
    }

    beforeEach(function () {
        $this->ticket = ticketOf([ticketLine($this->tusker, 2)]);
        syncTickets([$this->ticket])->assertOk();
        $this->otherTill = enrolFor($this->tenant, $this->branch);
    });

    it('lets one till at a time take payment for a ticket, and the same sale ask again', function () {
        $sale = (string) Str::uuid();

        holdTicket($this->ticket['uuid'], $sale)->assertOk()->assertJsonPath('status', 'held');
        holdTicket($this->ticket['uuid'], $sale)->assertOk();
        holdTicket($this->ticket['uuid'], (string) Str::uuid(), $this->otherTill)->assertConflict()->assertJsonPath('code', 'ticket_being_paid');

        // The cashier went back: the other till may take it now.
        holdTicket($this->ticket['uuid'], $sale, method: 'DELETE')->assertOk();
        holdTicket($this->ticket['uuid'], (string) Str::uuid(), $this->otherTill)->assertOk();
    });

    it('lets another till take over a hold that lapsed', function () {
        holdTicket($this->ticket['uuid'], (string) Str::uuid())->assertOk();
        $this->travel(16)->minutes();

        holdTicket($this->ticket['uuid'], (string) Str::uuid(), $this->otherTill)->assertOk();
    });

    it('refuses to take payment for a ticket already paid, even by a sale that came before its ticket', function () {
        $sale = saleOf([[$this->tusker, 2]], overrides: ['ticket_uuid' => $this->ticket['uuid'], 'placed_at' => '2026-10-27T13:50:00+03:00']);
        sync([$sale])->assertJsonPath('results.0.status', 'accepted');

        holdTicket($this->ticket['uuid'], (string) Str::uuid(), $this->otherTill)->assertConflict()->assertJsonPath('code', 'ticket_paid');

        $early = ticketOf([ticketLine($this->tusker)]);
        sync([saleOf([[$this->tusker, 1]], 2, ['ticket_uuid' => $early['uuid']])])->assertOk();
        syncTickets([$early])->assertOk();
        holdTicket($early['uuid'], (string) Str::uuid())->assertConflict()->assertJsonPath('code', 'ticket_paid');
    });

    it('says when the server does not have the ticket yet, and never holds another branch’s', function () {
        holdTicket((string) Str::uuid(), (string) Str::uuid())->assertNotFound()->assertJsonPath('code', 'unknown_ticket');

        $elsewhere = $this->tenant->run(fn () => Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Restaurant]));
        holdTicket($this->ticket['uuid'], (string) Str::uuid(), enrolFor($this->tenant, $elsewhere))->assertNotFound();

        [$otherTenant, $otherBranch] = tenantWithBranch(BusinessType::Restaurant);
        holdTicket($this->ticket['uuid'], (string) Str::uuid(), enrolFor($otherTenant, $otherBranch))->assertNotFound();
    });

    it('tells the owner when two sales paid the same ticket anyway, keeping both', function () {
        $first = saleOf([[$this->tusker, 2]], overrides: ['ticket_uuid' => $this->ticket['uuid']]);
        sync([$first])->assertOk();

        $this->enrolment = $this->otherTill;
        $second = saleOf([[$this->tusker, 2]], overrides: ['ticket_uuid' => $this->ticket['uuid']]);
        sync([$second])->assertJsonPath('results.0.status', 'accepted');

        $check = $this->tenant->run(fn () => MpesaCheck::query()->sole());
        expect($check->kind)->toBe(MpesaCheck::TICKET_PAID_TWICE)
            ->and($check->details['order_ids'])->toBe([$first['uuid'], $second['uuid']])
            ->and($this->tenant->run(fn () => Order::query()->count()))->toBe(2);
    });

    it('needs a sale UUID and nothing else', function () {
        holdTicket($this->ticket['uuid'], 'not-a-uuid')->assertUnprocessable()->assertJsonValidationErrors('sale_uuid');
    });
});

describe('taking sent lines off', function () {
    /** @return array<string, mixed> */
    function lineVoid(array $line, int $quantity = 1, string $voidedAt = '2026-10-27T13:20:00+03:00', array $overrides = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(),
            'line_uuid' => $line['uuid'],
            'quantity' => $quantity,
            'voided_at' => $voidedAt,
            'voided_by' => sellerFor(saleTenant()),
            'approved_by' => approverFor(saleTenant()),
            'reason' => 'changed_mind',
            'note' => null,
        ], $overrides);
    }

    beforeEach(function () {
        $this->beers = ticketLine($this->tusker, 3);
        $this->water1 = ticketLine($this->water, 1);
        $this->ticket = ticketOf([$this->beers, $this->water1]);
        syncTickets([$this->ticket])->assertOk();
    });

    it('takes some of a line off, once however often it is sent, and tells the branch’s tills', function () {
        $oneLess = lineVoid($this->beers);
        $withVoid = ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [$oneLess]]);
        Event::fake([TillsShouldSync::class]);

        syncTickets([$withVoid])->assertJsonPath('results.0.status', 'updated')->assertJsonMissingPath('results.0.refused_voids');
        syncTickets([$withVoid])->assertJsonPath('results.0.status', 'duplicate');
        expect(Event::dispatched(TillsShouldSync::class, fn (TillsShouldSync $e) => $e->what === TillsShouldSync::TICKETS))->toHaveCount(1);

        // Two Tusker and the water are left to pay; the line itself still says three.
        openTickets()
            ->assertJsonPath('tickets.0.total_cents', 2 * 35_000 + 5_000)
            ->assertJsonPath('tickets.0.lines.0.quantity', 3)
            ->assertJsonPath('tickets.0.voids.0', [
                'id' => $oneLess['uuid'], 'line_id' => $this->beers['uuid'], 'quantity' => 1, 'voided_at' => '2026-10-27T10:20:00+00:00',
                'voided_by' => $oneLess['voided_by'], 'approved_by' => $oneLess['approved_by'], 'reason' => 'changed_mind', 'note' => null,
            ]);

        expect($this->tenant->run(fn () => TicketLineVoid::query()->sole()->approver_allowed))->toBeTrue();
    });

    it('takes a whole line off in steps, never more than was sent', function () {
        $tooMany = lineVoid($this->beers, 2);

        $result = syncTickets([ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [
            lineVoid($this->beers, 2),
            $tooMany,
            lineVoid($this->beers, 1),
            lineVoid($this->water1, 1, overrides: ['reason' => 'other', 'note' => 'Spilled']),
        ]])])->assertOk()->json('results.0');

        expect($result['refused_voids'])->toBe([$tooMany['uuid']]);
        openTickets()->assertJsonPath('tickets.0.total_cents', 0)->assertJsonCount(3, 'tickets.0.voids');
    });

    it('reopens a billed ticket when a line comes off after the bill was printed', function () {
        syncTickets([ticketOf([], ['uuid' => $this->ticket['uuid'], 'bill_requested_at' => '2026-10-27T13:15:00+03:00'])])
            ->assertJsonPath('results.0.ticket_status', 'bill_requested');

        syncTickets([ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [lineVoid($this->beers)]])])
            ->assertJsonPath('results.0.ticket_status', 'open');

        // A fresh bill after the void stands.
        syncTickets([ticketOf([], ['uuid' => $this->ticket['uuid'], 'bill_requested_at' => '2026-10-27T13:25:00+03:00'])])
            ->assertJsonPath('results.0.ticket_status', 'bill_requested');
    });

    it('refuses a void once the ticket is paid, or while another till takes its payment', function () {
        $otherTill = enrolFor($this->tenant, $this->branch);
        $sale = (string) Str::uuid();
        app('auth')->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$otherTill->plainTextToken, 'X-Device-ID' => $otherTill->device->id])
            ->postJson("/api/v1/tickets/{$this->ticket['uuid']}/hold", ['sale_uuid' => $sale])->assertJsonPath('status', 'held');

        $whileHeld = lineVoid($this->beers);
        syncTickets([ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [$whileHeld]])])->assertJsonPath('results.0.refused_voids', [$whileHeld['uuid']]);

        // The till holding it may still take a line off before it takes the money.
        syncTickets([ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [lineVoid($this->water1)]])], $otherTill)->assertJsonMissingPath('results.0.refused_voids');

        sync([saleOf([[$this->tusker, 3]], overrides: ['uuid' => $sale, 'ticket_uuid' => $this->ticket['uuid'], 'placed_at' => '2026-10-27T13:50:00+03:00'])])->assertOk();

        $late = lineVoid($this->beers, voidedAt: '2026-10-27T13:55:00+03:00');
        syncTickets([ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [$late]])])->assertJsonPath('results.0.refused_voids', [$late['uuid']]);

        expect($this->tenant->run(fn () => TicketLineVoid::query()->count()))->toBe(1);
    });

    it('keeps a void made before an offline payment on the same till, when the ticket arrives after the sale', function () {
        $line = ticketLine($this->tusker, 2);
        $void = lineVoid($line, 1, '2026-10-27T13:20:00+03:00');
        $ticket = ticketOf([$line], ['voids' => [$void]]);
        sync([saleOf([[$this->tusker, 1]], overrides: ['ticket_uuid' => $ticket['uuid'], 'placed_at' => '2026-10-27T13:50:00+03:00'])])->assertOk();

        syncTickets([$ticket])->assertJsonPath('results.0.ticket_status', 'closed')->assertJsonMissingPath('results.0.refused_voids');
        expect($this->tenant->run(fn () => TicketLineVoid::query()->whereKey($void['uuid'])->exists()))->toBeTrue();
    });

    it('records, and flags, a void approved by someone who may not void', function () {
        syncTickets([ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [lineVoid($this->beers, overrides: ['approved_by' => sellerFor(saleTenant())])]])])
            ->assertJsonMissingPath('results.0.refused_voids');

        expect($this->tenant->run(fn () => TicketLineVoid::query()->sole()->approver_allowed))->toBeFalse();
    });

    it('refuses a line of another ticket, a reused void UUID, unknown staff, unknown fields and the payment reason', function () {
        $otherLine = ticketLine($this->water, 1);
        $other = ticketOf([$otherLine], ['table_id' => null]);
        syncTickets([$other])->assertOk();
        $void = lineVoid($this->beers);
        syncTickets([ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [$void]])])->assertOk();
        $stray = lineVoid($otherLine);

        $results = syncTickets([
            ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [$stray]]),
            ticketOf([], ['uuid' => $other['uuid'], 'table_id' => null, 'voids' => [lineVoid($otherLine, overrides: ['uuid' => $void['uuid']])]]),
            ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [lineVoid($this->beers, overrides: ['approved_by' => (string) Str::uuid()])]]),
            ticketOf([], ['uuid' => $this->ticket['uuid'], 'voids' => [lineVoid($this->beers, overrides: ['reason' => 'payment_problem', 'branch_id' => (string) Str::uuid()])]]),
        ])->assertOk()->json('results');

        expect($results[0]['refused_voids'])->toBe([$stray['uuid']])
            ->and(array_column(array_slice($results, 1), 'reason'))->toBe(['void_conflict', 'unknown_staff', 'invalid'])
            ->and(array_keys($results[3]['errors']))->toEqualCanonicalizing(['voids.0.reason', 'voids.0.branch_id'])
            ->and($this->tenant->run(fn () => TicketLineVoid::query()->count()))->toBe(1);
    });
});
