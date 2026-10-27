<?php

declare(strict_types=1);

use App\Jobs\SyncTillJob;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\FloorArea;
use App\Models\Item;
use App\Models\StaffMember;
use App\Models\SyncState;
use App\Security\SecretBox;
use App\Sync\SyncCatalogue;
use App\Sync\SyncStaff;
use App\Sync\SyncTill;
use App\Till\Till;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function item(string $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id, 'category_id' => 'c1', 'type' => 'product', 'name' => "Item {$id}", 'sku' => null,
        'barcode' => null, 'price_cents' => 10000, 'vat_category' => 'standard', 'is_active' => true,
    ], $overrides);
}

function catalogueResponse(array $items, bool $full = true, string $syncedAt = '2026-10-25T08:00:00+00:00'): array
{
    return ['synced_at' => $syncedAt, 'full' => $full, 'categories' => [['id' => 'c1', 'name' => 'Drinks', 'sort_order' => 1]], 'items' => $items];
}

function staffMember(string $id, string $name): array
{
    return ['id' => $id, 'name' => $name, 'role' => 'cashier', 'permissions' => ['orders.create', 'payments.take'], 'pin_hash' => '$2y$04$hash', 'pin_length' => 4];
}

function deviceResponse(string $accountStatus = 'active'): array
{
    return [
        'device' => ['id' => TEST_DEVICE_ID, 'name' => 'Till 1'],
        'branch' => ['id' => '0199b1f2-0000-7000-8000-000000000001', 'name' => 'Nyali', 'business_type' => 'restaurant', 'terminal_mode' => 'table_service'],
        'token_expires_at' => now()->addHours(12)->toIso8601String(),
        'account_status' => $accountStatus,
    ];
}

/** A healthy server; override any path with a response or a closure. */
function fakeServer(array $overrides = []): void
{
    $routes = array_merge([
        'device/token/renew' => Http::response(['token' => 'renewed-token', 'expires_at' => now()->addHours(12)->toIso8601String()], 201),
        'device/token' => Http::response(['token' => 'refreshed-token', 'expires_at' => now()->addHours(12)->toIso8601String()], 201),
        'device' => Http::response(deviceResponse()),
        'staff' => Http::response(['synced_at' => now()->toIso8601String(), 'staff' => [staffMember('s1', 'Amina')]]),
        'catalogue*' => Http::response(catalogueResponse([item('i1')])),
        'floor' => Http::response(['areas' => []]),
        'tickets' => Http::response(['tickets' => []]),
    ], $overrides);

    Http::fake(array_combine(
        array_map(fn (string $path) => TEST_API_URL."/api/v1/{$path}", array_keys($routes)),
        array_values($routes),
    ));
}

function sentTo(string $path): int
{
    return Http::recorded(fn (Request $request) => parse_url($request->url(), PHP_URL_PATH) === "/api/v1/{$path}")->count();
}

describe('menu', function () {
    it('downloads the whole menu the first time', function () {
        pairTill();
        fakeServer(['catalogue*' => Http::response(catalogueResponse([item('i1'), item('i2', ['price_cents' => 25050])]))]);

        app(SyncCatalogue::class)->handle();

        expect(Item::query()->count())->toBe(2)
            ->and(Item::query()->find('i2')?->price_cents)->toBe(25050)
            ->and(Category::query()->pluck('name')->all())->toBe(['Drinks'])
            ->and(SyncState::read(SyncCatalogue::CURSOR))->toBe('2026-10-25T08:00:00+00:00');
        Http::assertSent(fn (Request $request) => ! str_contains($request->url(), 'updated_since'));
    });

    it('applies only changes afterwards, including items switched off', function () {
        pairTill();
        fakeServer(['catalogue*' => Http::sequence()
            ->push(catalogueResponse([item('i1'), item('i2')]))
            ->push(catalogueResponse([item('i2', ['is_active' => false]), item('i3', ['name' => 'Chapati'])], full: false, syncedAt: '2026-10-25T09:00:00+00:00')),
        ]);

        app(SyncCatalogue::class)->handle();
        app(SyncCatalogue::class)->handle();

        expect(Item::query()->count())->toBe(3)
            ->and(Item::query()->find('i2')?->is_active)->toBeFalse()
            ->and(Item::query()->find('i3')?->name)->toBe('Chapati')
            ->and(SyncState::read(SyncCatalogue::CURSOR))->toBe('2026-10-25T09:00:00+00:00');
        Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'updated_since=2026-10-25T08:00:00+00:00'));
    });

    it('handles a supermarket-sized menu', function () {
        pairTill();
        fakeServer(['catalogue*' => Http::response(catalogueResponse(array_map(fn (int $n) => item("i{$n}"), range(1, 6000))))]);

        app(SyncCatalogue::class)->handle();

        expect(Item::query()->count())->toBe(6000);
    });
});

describe('floor plan', function () {
    it('replaces the floor plan in full, so tables switched off disappear', function () {
        pairTill();
        FloorArea::query()->create(['id' => 'old-area', 'name' => 'Gone', 'sort_order' => 1]);
        fakeServer(['floor' => Http::response(['areas' => [
            ['id' => 'a1', 'name' => 'Main hall', 'sort_order' => 1, 'tables' => [
                ['id' => 't1', 'name' => '1', 'seats' => 4, 'sort_order' => 1],
                ['id' => 't2', 'name' => '2', 'seats' => 2, 'sort_order' => 2],
            ]],
        ]])]);

        app(SyncTill::class)->handle();

        expect(FloorArea::query()->pluck('name')->all())->toBe(['Main hall'])
            ->and(FloorArea::query()->sole()->tables->pluck('name')->all())->toBe(['1', '2'])
            ->and(DiningTable::query()->find('t2')?->seats)->toBe(2);
    });
});

describe('staff', function () {
    it('replaces the staff list, so people removed on the server disappear', function () {
        pairTill();
        StaffMember::query()->create(SyncStaff::row(staffMember('old', 'Left Last Week'), app(SecretBox::class)));
        fakeServer();

        app(SyncTill::class)->handle();

        expect(StaffMember::query()->pluck('name')->all())->toBe(['Amina'])
            ->and(StaffMember::query()->find('s1')?->permissions)->toBe(['orders.create', 'payments.take']);
    });
});

describe('token', function () {
    it('leaves a token with plenty of time alone', function () {
        pairTill('current', CarbonImmutable::now()->addHours(10));
        fakeServer();

        app(SyncTill::class)->handle();

        expect(sentTo('device/token'))->toBe(0)
            ->and(app(Till::class)->credentials()['token'])->toBe('current');
    });

    it('refreshes a token that is close to running out', function () {
        pairTill('current', CarbonImmutable::now()->addHours(2));
        fakeServer();

        app(SyncTill::class)->handle();

        expect(sentTo('device/token'))->toBe(1)
            ->and(app(Till::class)->credentials()['token'])->toBe('refreshed-token');
    });

    it('renews a token that expired while the till was off', function () {
        pairTill('stale', CarbonImmutable::now()->subHours(9));
        fakeServer();

        app(SyncTill::class)->handle();

        expect(sentTo('device/token/renew'))->toBe(1)
            ->and(app(Till::class)->credentials()['token'])->toBe('renewed-token')
            ->and(StaffMember::query()->count())->toBe(1);
    });

    it('refreshes instead when the server says an expired-looking token is still valid', function () {
        pairTill('skewed', CarbonImmutable::now()->subMinute());
        fakeServer(['device/token/renew' => Http::response(['message' => 'still valid'], 409)]);

        app(SyncTill::class)->handle();

        expect(app(Till::class)->credentials()['token'])->toBe('refreshed-token');
    });
});

describe('a sync round', function () {
    it('notes success and the account status', function () {
        pairTill();
        fakeServer(['device' => Http::response(deviceResponse('suspended'))]);

        app(SyncTill::class)->handle();

        expect(SyncState::read(SyncTill::LAST_SUCCESS))->not->toBeNull()
            ->and(SyncState::read(SyncTill::LAST_ERROR))->toBeNull()
            ->and(SyncState::read(SyncTill::ACCOUNT_STATUS))->toBe('suspended');
    });

    it('treats being offline as normal and keeps everything', function () {
        pairTill();
        Item::query()->create(item('i1'));
        Http::fake(fn () => throw new ConnectionException('Network is unreachable'));

        app(SyncTill::class)->handle();

        expect(app(Till::class)->isPaired())->toBeTrue()
            ->and(Item::query()->count())->toBe(1)
            ->and(SyncState::read(SyncTill::LAST_ERROR))->toContain('ApiUnavailable');
    });

    it('does not use an expired token when renewal could not reach the server', function () {
        pairTill('stale', CarbonImmutable::now()->subHours(9));
        fakeServer(['device/token/renew' => Http::response([], 503)]);

        app(SyncTill::class)->handle();

        expect(sentTo('device'))->toBe(0)
            ->and(sentTo('staff'))->toBe(0)
            ->and(app(Till::class)->isPaired())->toBeTrue();
    });

    it('recovers from one refused request by renewing the token', function () {
        pairTill();
        fakeServer(['device' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        app(SyncTill::class)->handle();

        expect(app(Till::class)->isPaired())->toBeTrue()
            ->and(app(Till::class)->credentials()['token'])->toBe('renewed-token');
    });

    it('marks the till removed only when renewal is refused too', function () {
        pairTill();
        Item::query()->create(item('i1'));
        fakeServer([
            'device/token/renew' => Http::response(['message' => 'Unauthenticated.'], 401),
            'device' => Http::response(['message' => 'Unauthenticated.'], 401),
        ]);

        app(SyncTill::class)->handle();

        expect(app(Till::class)->isRemoved())->toBeTrue()
            ->and(Item::query()->count())->toBe(1);
    });

    it('does nothing before the till is paired', function () {
        Http::fake();

        app(SyncTill::class)->handle();

        Http::assertNothingSent();
    });
});

describe('the background job', function () {
    it('queues the next round while the till is paired', function () {
        pairTill();
        fakeServer();
        Queue::fake();

        (new SyncTillJob)->handle(app(SyncTill::class), app(Till::class));

        Queue::assertPushed(SyncTillJob::class, fn (SyncTillJob $job) => $job->delay !== null);
    });

    it('queues the next round even after an unexpected failure', function () {
        pairTill();
        Http::fake(fn () => throw new RuntimeException('boom'));
        Queue::fake();

        expect(fn () => (new SyncTillJob)->handle(app(SyncTill::class), app(Till::class)))->toThrow(RuntimeException::class);
        Queue::assertPushed(SyncTillJob::class);
    });

    it('stops once the till is removed', function () {
        pairTill();
        fakeServer([
            'device/token/renew' => Http::response([], 401),
            'device' => Http::response([], 401),
        ]);
        Queue::fake();

        (new SyncTillJob)->handle(app(SyncTill::class), app(Till::class));

        Queue::assertNothingPushed();
    });
});
