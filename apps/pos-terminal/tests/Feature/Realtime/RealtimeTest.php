<?php

declare(strict_types=1);

use App\Livewire\SignIn;
use App\Livewire\StatusBar;
use App\Models\StaffMember;
use App\Models\SyncState;
use App\Security\SecretBox;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use App\Sync\SyncTill;
use App\Till\Till;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const REALTIME = ['key' => 'pub-key', 'host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'channel' => 'till.'.TEST_DEVICE_ID, 'event' => 'till.sync'];

function serverWith(array $staff, ?array $realtime = REALTIME, int $deviceStatus = 200): void
{
    Http::fake([
        TEST_API_URL.'/api/v1/device/token/renew' => Http::response([], 401),
        TEST_API_URL.'/api/v1/device' => Http::response([
            'device' => ['id' => TEST_DEVICE_ID, 'name' => 'Till 1'],
            'branch' => ['id' => '0199b1f2-0000-7000-8000-000000000001', 'name' => 'Nyali', 'business_type' => 'restaurant', 'terminal_mode' => 'table_service'],
            'token_expires_at' => now()->addHours(12)->toIso8601String(),
            'account_status' => 'active',
            'realtime' => $realtime,
        ], $deviceStatus),
        TEST_API_URL.'/api/v1/staff' => Http::response(['synced_at' => now()->toIso8601String(), 'staff' => $staff]),
        TEST_API_URL.'/api/v1/floor' => Http::response(['areas' => []]),
        TEST_API_URL.'/api/v1/tickets' => Http::response(['tickets' => []]),
        TEST_API_URL.'/api/v1/catalogue*' => Http::response(['synced_at' => now()->toIso8601String(), 'full' => true, 'categories' => [], 'items' => []]),
    ]);
}

function person(string $id, string $name): array
{
    return ['id' => $id, 'name' => $name, 'role' => 'cashier', 'permissions' => ['orders.take'], 'pin_hash' => password_hash('2468', PASSWORD_BCRYPT, ['cost' => 4]), 'pin_length' => 4];
}

beforeEach(fn () => pairTill());

describe('where to listen', function () {
    it('keeps the listening details from each sync', function () {
        serverWith([]);
        app(SyncTill::class)->handle();

        expect(json_decode((string) SyncState::read(SyncTill::REALTIME), true))->toBe(REALTIME);
    });

    // Its own test: a second Http::fake adds stubs behind the first instead of replacing them.
    it('drops them when the server turns it off', function () {
        SyncState::write(SyncTill::REALTIME, (string) json_encode(REALTIME));
        serverWith([], realtime: null);
        app(SyncTill::class)->handle();

        expect(SyncState::read(SyncTill::REALTIME))->toBeNull();
    });

    it('puts only the public details in the page', function () {
        SyncState::write(SyncTill::REALTIME, (string) json_encode(REALTIME));

        $page = $this->get(route('sign-in'))->assertOk();

        $page->assertSee('data-vendapos-realtime', false)->assertSee('pub-key');
        expect($page->getContent())->not->toContain('device-token-1');
    });

    it('leaves the page alone while there is nothing to listen to', function () {
        $this->get(route('sign-in'))->assertDontSee('data-vendapos-realtime', false);
    });

    // The window keeps looking for these, so a till paired a moment ago starts listening without a reload.
    it('hands them to an open screen as soon as a sync brings them', function () {
        $bar = Livewire::test(StatusBar::class)->assertDontSeeHtml('data-vendapos-realtime');

        SyncState::write(SyncTill::REALTIME, (string) json_encode(REALTIME));

        $bar->call('$refresh')->assertSeeHtml('data-vendapos-realtime');
    });
});

describe('signing the subscription', function () {
    it('signs this till’s channel with the server, keeping the token in PHP', function () {
        Http::fake([TEST_API_URL.'/api/broadcasting/auth' => Http::response(['auth' => 'pub-key:signature'])]);

        $this->postJson(route('realtime.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-till.'.TEST_DEVICE_ID])
            ->assertOk()
            ->assertExactJson(['auth' => 'pub-key:signature']);

        Http::assertSent(fn (Request $request) => $request->url() === TEST_API_URL.'/api/broadcasting/auth'
            && $request->hasHeader('Authorization', 'Bearer device-token-1')
            && $request->hasHeader('X-Device-ID', TEST_DEVICE_ID)
            && $request['channel_name'] === 'private-till.'.TEST_DEVICE_ID);
    });

    it('never asks for another till’s channel', function () {
        Http::fake();

        $this->postJson(route('realtime.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-till.someone-else'])->assertForbidden();

        Http::assertNothingSent();
    });

    it('refuses on an unpaired or removed till', function () {
        Http::fake();
        app(Till::class)->markRemoved();

        $this->postJson(route('realtime.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-till.'.TEST_DEVICE_ID])->assertForbidden();
        Http::assertNothingSent();
    });

    it('passes on a refusal, and reports an unreachable server as unavailable', function () {
        Http::fake([TEST_API_URL.'/*' => Http::response([], 403)]);
        $this->postJson(route('realtime.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-till.'.TEST_DEVICE_ID])->assertForbidden();

        Http::fake(fn () => throw new ConnectionException('down'));
        $this->postJson(route('realtime.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-till.'.TEST_DEVICE_ID])->assertServiceUnavailable();
    });

    it('rejects a malformed socket id', function () {
        $this->postJson(route('realtime.auth'), ['socket_id' => 'x', 'channel_name' => 'private-till.'.TEST_DEVICE_ID])->assertUnprocessable();
    });
});

describe('when the server says sync now', function () {
    it('syncs at once and tells the screens to redraw', function () {
        serverWith([person('s1', 'Amina'), person('s2', 'Brian')]);

        Livewire::test(StatusBar::class)
            ->dispatch('till-sync', what: 'staff')
            ->assertDispatched('till-updated')
            ->assertNoRedirect();

        expect(StaffMember::query()->pluck('name')->all())->toBe(['Amina', 'Brian']);
    });

    it('signs out someone the server just deactivated', function () {
        $amina = StaffMember::query()->create(SyncStaff::row(person('s1', 'Amina'), app(SecretBox::class)));
        app(TillSession::class)->signIn($amina);
        serverWith([person('s2', 'Brian')]);

        Livewire::test(StatusBar::class)
            ->dispatch('till-sync', what: 'staff')
            ->assertRedirect(route('sign-in'));

        expect(app(TillSession::class)->hasSignedIn())->toBeFalse();
    });

    it('keeps someone signed in when others change', function () {
        $amina = StaffMember::query()->create(SyncStaff::row(person('s1', 'Amina'), app(SecretBox::class)));
        app(TillSession::class)->signIn($amina);
        serverWith([person('s1', 'Amina'), person('s2', 'Brian')]);

        Livewire::test(StatusBar::class)
            ->dispatch('till-sync', what: 'staff')
            ->assertNoRedirect();

        expect(app(TillSession::class)->staff()?->id)->toBe('s1');
    });

    it('locks a till the server just revoked', function () {
        serverWith([], deviceStatus: 401);

        Livewire::test(StatusBar::class)
            ->dispatch('till-sync', what: 'device')
            ->assertRedirect(route('pair'));

        expect(app(Till::class)->isRemoved())->toBeTrue();
    });

    it('drops a chosen name from the PIN pad when that person is gone', function () {
        StaffMember::query()->create(SyncStaff::row(person('s1', 'Amina'), app(SecretBox::class)));
        $screen = Livewire::test(SignIn::class)->call('choose', 's1')->assertSet('staffId', 's1');

        StaffMember::query()->whereKey('s1')->delete();

        $screen->dispatch('till-updated')->assertSet('staffId', null);
    });
});
