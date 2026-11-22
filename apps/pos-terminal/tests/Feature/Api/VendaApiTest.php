<?php

declare(strict_types=1);

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\Exceptions\TokenStillValid;
use App\Api\VendaApi;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function pairResponse(): array
{
    return [
        'device' => ['id' => TEST_DEVICE_ID, 'name' => 'Till 1'],
        'business' => ['name' => 'Danamo Restaurant'],
        'branch' => ['id' => '0199b1f2-0000-7000-8000-000000000001', 'name' => 'Nyali', 'business_type' => 'restaurant', 'terminal_mode' => 'table_service'],
        'token' => 'plain-token',
        'token_expires_at' => '2026-10-25T21:00:00+03:00',
    ];
}

it('pairs with a code and reads the server answer', function () {
    Http::fake([TEST_API_URL.'/api/v1/device/pair' => Http::response(pairResponse(), 201)]);

    $result = app(VendaApi::class)->pair('ABCD-EFGH');

    expect($result->deviceId)->toBe(TEST_DEVICE_ID)
        ->and($result->businessName)->toBe('Danamo Restaurant')
        ->and($result->branch->terminalMode)->toBe('table_service')
        ->and($result->token->plainText)->toBe('plain-token')
        ->and($result->token->expiresAt?->toIso8601String())->toBe('2026-10-25T18:00:00+00:00');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['code'] === 'ABCD-EFGH'
        && ! $request->hasHeader('Authorization'));
});

it('sends the decrypted token and device id on authorised calls', function () {
    pairTill('secret-token');
    Http::fake([TEST_API_URL.'/api/v1/staff' => Http::response(['synced_at' => now()->toIso8601String(), 'staff' => []])]);

    app(VendaApi::class)->staff();

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request->hasHeader('X-Device-ID', TEST_DEVICE_ID)
        && $request->hasHeader('Accept', 'application/json'));
});

it('asks for catalogue changes since a time', function () {
    pairTill();
    Http::fake([TEST_API_URL.'/api/v1/catalogue*' => Http::response([
        'synced_at' => '2026-10-25T08:00:00+00:00',
        'full' => false,
        'categories' => [['id' => 'c1', 'name' => 'Drinks', 'sort_order' => 1]],
        'items' => [['id' => 'i1', 'category_id' => 'c1', 'type' => 'product', 'name' => 'Soda', 'sku' => null, 'barcode' => '6161', 'price_cents' => 8000, 'vat_category' => 'standard', 'is_active' => false]],
    ])]);

    $snapshot = app(VendaApi::class)->catalogue(CarbonImmutable::parse('2026-10-25T07:00:00Z'));

    expect($snapshot->full)->toBeFalse()
        ->and($snapshot->items[0]['price_cents'])->toBe(8000)
        ->and($snapshot->items[0]['is_active'])->toBeFalse()
        ->and($snapshot->categories[0]['name'])->toBe('Drinks');

    Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'updated_since=2026-10-25T07:00:00+00:00'));
});

it('turns server answers into the right errors', function (int $status, string $exception) {
    pairTill();
    Http::fake([TEST_API_URL.'/*' => Http::response(['message' => 'Nope.'], $status)]);

    expect(fn () => app(VendaApi::class)->device())->toThrow($exception);
})->with([
    'unauthenticated' => [401, TillNotAuthorised::class],
    'forbidden' => [403, TillNotAuthorised::class],
    'still valid' => [409, TokenStillValid::class],
    'rate limited' => [429, ApiUnavailable::class],
    'server error' => [500, ApiUnavailable::class],
]);

it('passes on the server message when it refuses a request', function () {
    Http::fake([TEST_API_URL.'/*' => Http::response(['message' => 'This code can’t be used. Ask for a new one.'], 422)]);

    expect(fn () => app(VendaApi::class)->pair('WRONG'))
        ->toThrow(ApiRejected::class, 'This code can’t be used. Ask for a new one.');
});

it('reports an unreachable server as unavailable', function () {
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    expect(fn () => app(VendaApi::class)->pair('ABCD-EFGH'))->toThrow(ApiUnavailable::class);
});

it('treats an answer missing required fields as unavailable', function () {
    Http::fake([TEST_API_URL.'/*' => Http::response(['device' => ['id' => TEST_DEVICE_ID]], 201)]);

    expect(fn () => app(VendaApi::class)->pair('ABCD-EFGH'))->toThrow(ApiUnavailable::class);
});

it('refuses to call anywhere when no API address was built in', function () {
    config(['terminal.api_url' => null]);
    Http::fake();

    expect(fn () => app(VendaApi::class)->pair('ABCD-EFGH'))->toThrow(ApiNotConfigured::class);
    Http::assertNothingSent();
});
