<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\OrderData;
use VendaPOS\Core\Data\TicketData;
use VendaPOS\Core\Data\TicketLineData;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\Tax\OrderTotals;

/** @return array<string, mixed> */
function ticketPayload(array $overrides = []): array
{
    return array_merge([
        'uuid' => fake()->uuid(),
        'staff_id' => fake()->uuid(),
        'opened_at' => '2026-10-27T13:05:00+03:00',
        'table_id' => fake()->uuid(),
        'label' => null,
        'lines' => [
            [
                'uuid' => fake()->uuid(),
                'item_id' => fake()->uuid(),
                'name' => 'Tusker',
                'quantity' => 3,
                'unit_price_cents' => 35_000,
                'vat_category' => 'standard',
                'staff_id' => fake()->uuid(),
                'sent_at' => '2026-10-27T13:06:00+03:00',
            ],
        ],
    ], $overrides);
}

it('creates from a valid payload, with no bill yet', function () {
    $ticket = TicketData::from(ticketPayload());

    expect($ticket->lineList())->toHaveCount(1)
        ->and($ticket->bill_requested_at)->toBeNull()
        ->and($ticket->lineList()[0]->vat_category)->toBe(VatCategory::Standard);
});

it('turns a ticket line into a sale line the shared totals can add up', function () {
    $line = TicketData::from(ticketPayload())->lineList()[0];

    $orderLine = $line->toOrderLine();
    $totals = OrderTotals::fromLines([$orderLine]);

    expect($orderLine->line_total_cents)->toBe(105_000)
        ->and($orderLine->name)->toBe('Tusker')
        ->and($totals->total->cents)->toBe(105_000);
});

it('allows a takeaway ticket with no table, and refuses a missing table key', function () {
    expect(Validator::make(ticketPayload(['table_id' => null, 'label' => 'Otieno']), TicketData::validationRules())->passes())->toBeTrue();

    $payload = ticketPayload();
    unset($payload['table_id']);

    expect(Validator::make($payload, TicketData::validationRules())->errors()->keys())->toBe(['table_id']);
});

it('refuses bad lines', function () {
    $payload = ticketPayload();
    $payload['lines'][0]['quantity'] = 0;
    $payload['lines'][0]['uuid'] = 'not-a-uuid';

    expect(Validator::make($payload, TicketData::validationRules())->errors()->keys())
        ->toEqualCanonicalizing(['lines.0.quantity', 'lines.0.uuid']);
});

it('carries no tenant, branch, device or price total: the server works those out', function () {
    $properties = array_map(fn (ReflectionProperty $p) => $p->getName(), (new ReflectionClass(TicketData::class))->getProperties());
    $lineProperties = array_map(fn (ReflectionProperty $p) => $p->getName(), (new ReflectionClass(TicketLineData::class))->getProperties());

    expect($properties)->not->toContain('tenant_id', 'branch_id', 'device_id', 'total_cents')
        ->and($lineProperties)->not->toContain('line_total_cents');
});

it('lets an order name the ticket it pays, and leaves it out for a quick sale', function () {
    $ticketId = fake()->uuid();

    expect(OrderData::from(orderPayload(['ticket_uuid' => $ticketId]))->ticket_uuid)->toBe($ticketId)
        ->and(OrderData::from(orderPayload())->ticket_uuid)->toBeNull()
        ->and(Validator::make(orderPayload(['ticket_uuid' => 'nope']), OrderData::validationRules())->errors()->keys())->toBe(['ticket_uuid']);
});
