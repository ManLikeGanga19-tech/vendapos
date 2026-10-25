<?php

declare(strict_types=1);

use App\Jobs\SyncTillJob;
use App\Livewire\PairTill;
use App\Livewire\TillReady;
use App\Models\Item;
use App\Models\Preference;
use App\Models\StaffMember;
use App\Till\PairingFailed;
use App\Till\PairThisTill;
use App\Till\Till;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeSecretBox;

beforeEach(fn () => Queue::fake());

function pairAnswer(): array
{
    return [
        'device' => ['id' => TEST_DEVICE_ID, 'name' => 'Till 1'],
        'business' => ['name' => 'Danamo Restaurant'],
        'branch' => ['id' => '0199b1f2-0000-7000-8000-000000000001', 'name' => 'Nyali', 'business_type' => 'restaurant', 'terminal_mode' => 'table_service'],
        'token' => 'paired-token',
        'token_expires_at' => now()->addHours(12)->toIso8601String(),
    ];
}

function fakePairingServer(array $overrides = []): void
{
    Http::fake(array_merge([
        TEST_API_URL.'/api/v1/device/pair' => Http::response(pairAnswer(), 201),
        TEST_API_URL.'/api/v1/staff' => Http::response(['synced_at' => now()->toIso8601String(), 'staff' => [
            ['id' => 's1', 'name' => 'Amina', 'role' => 'cashier', 'permissions' => [], 'pin_hash' => '$2y$04$x', 'pin_length' => 4],
        ]]),
        TEST_API_URL.'/api/v1/catalogue*' => Http::response(['synced_at' => now()->toIso8601String(), 'full' => true, 'categories' => [], 'items' => [
            ['id' => 'i1', 'category_id' => null, 'type' => 'product', 'name' => 'Soda', 'sku' => null, 'barcode' => null, 'price_cents' => 8000, 'vat_category' => 'standard', 'is_active' => true],
        ]]),
    ], $overrides));
}

function pairingFailure(callable $call): ?string
{
    try {
        $call();
    } catch (PairingFailed $e) {
        return $e->reason;
    }

    return null;
}

describe('pairing', function () {
    it('pairs, downloads staff and menu, and starts the background sync', function () {
        fakePairingServer();

        $downloaded = app(PairThisTill::class)->handle('abcd-2345');

        expect($downloaded)->toBeTrue()
            ->and(app(Till::class)->isPaired())->toBeTrue()
            ->and(app(Till::class)->credentials()['token'])->toBe('paired-token')
            ->and(StaffMember::query()->count())->toBe(1)
            ->and(Item::query()->count())->toBe(1);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/device/pair') && $request['code'] === 'ABCD2345');
        Queue::assertPushed(SyncTillJob::class);
    });

    it('stays paired when the first download fails', function () {
        fakePairingServer([TEST_API_URL.'/api/v1/staff' => Http::response([], 503)]);

        expect(app(PairThisTill::class)->handle('ABCD2345'))->toBeFalse()
            ->and(app(Till::class)->isPaired())->toBeTrue();
        Queue::assertPushed(SyncTillJob::class);
    });

    it('rejects a malformed code without asking the server', function (string $code) {
        Http::fake();

        expect(pairingFailure(fn () => app(PairThisTill::class)->handle($code)))->toBe('code_invalid');
        Http::assertNothingSent();
    })->with(['too short' => 'ABCD234', 'too long' => 'ABCD23456', 'look-alike O' => 'ABCD234O', 'look-alike 1' => 'ABCD2341']);

    it('does not use up the code when the keyring is unavailable', function () {
        FakeSecretBox::$available = false;
        Http::fake();

        expect(pairingFailure(fn () => app(PairThisTill::class)->handle('ABCD2345')))->toBe('keyring');
        Http::assertNothingSent();
    });

    it('explains each server refusal', function (mixed $answer, string $reason) {
        Http::fake([TEST_API_URL.'/*' => $answer]);

        expect(pairingFailure(fn () => app(PairThisTill::class)->handle('ABCD2345')))->toBe($reason)
            ->and(app(Till::class)->identity())->toBeNull();
    })->with([
        'used or expired code' => [fn () => Http::response(['message' => 'This code can’t be used.'], 422), 'code_refused'],
        'rate limited' => [fn () => Http::response([], 429), 'too_many_tries'],
        'server error' => [fn () => Http::response([], 500), 'offline'],
        'offline' => [fn () => throw new ConnectionException('down'), 'offline'],
    ]);

    it('explains a till built without a server address', function () {
        config(['terminal.api_url' => '']);

        expect(pairingFailure(fn () => app(PairThisTill::class)->handle('ABCD2345')))->toBe('not_configured');
    });
});

describe('screens', function () {
    it('sends an unpaired till to the pairing screen', function () {
        $this->get('/')->assertRedirect(route('pair'));
        $this->get(route('ready'))->assertRedirect(route('pair'));
    });

    it('sends a paired till to the ready screen', function () {
        pairTill();

        $this->get('/')->assertRedirect(route('ready'));
        $this->get(route('pair'))->assertRedirect(route('ready'));
    });

    it('builds the code from allowed keys only, up to 8', function () {
        Livewire::test(PairTill::class)
            ->call('press', 'A')->call('press', 'O')->call('press', '1')->call('press', 'b')
            ->assertSet('code', 'AB')
            ->set('code', 'cd-2345 xyzw')
            ->assertSet('code', 'CD2345XY')
            ->call('backspace')
            ->assertSet('code', 'CD2345X')
            ->call('clear')
            ->assertSet('code', '');
    });

    it('shows eight boxes, filled as the code is typed', function () {
        Livewire::test(PairTill::class)
            ->assertViewHas('boxes', [['', '', '', ''], ['', '', '', '']])
            ->call('press', 'A')->call('press', '2')
            ->assertViewHas('boxes', [['A', '2', '', ''], ['', '', '', '']]);
    });

    it('offers every number and letter a code can have, and only those', function () {
        Livewire::test(PairTill::class)
            ->assertViewHas('digits', ['2', '3', '4', '5', '6', '7', '8', '9'])
            ->assertViewHas('letters', fn (array $letters) => count($letters) === 22 && array_intersect($letters, ['I', 'L', 'O', 'U']) === []);
    });

    it('explains why 0 or O typed on a keyboard does not appear', function () {
        Livewire::test(PairTill::class)
            ->call('press', 'A')
            ->call('press', '0')
            ->assertSet('code', 'A')
            ->assertSet('error', __('terminal.pair.not_used'))
            ->call('press', 'B')
            ->assertSet('error', null);
    });

    it('pairs and moves on to the ready screen', function () {
        fakePairingServer();

        Livewire::test(PairTill::class)
            ->set('code', 'ABCD2345')
            ->call('pair')
            ->assertRedirect(route('ready'));
    });

    it('shows why pairing failed', function () {
        Http::fake([TEST_API_URL.'/*' => Http::response(['message' => 'No.'], 422)]);

        Livewire::test(PairTill::class)
            ->set('code', 'ABCD2345')
            ->call('pair')
            ->assertSet('error', __('terminal.pair.code_refused'))
            ->assertSee(__('terminal.pair.code_refused'))
            ->assertNoRedirect();
    });

    it('tells a removed till to ask a manager', function () {
        pairTill();
        app(Till::class)->markRemoved();

        Livewire::test(PairTill::class)->assertSee(__('terminal.pair.removed'));
    });

    it('switches to Swahili and remembers it, even after pairing again', function () {
        Livewire::test(PairTill::class)
            ->call('useLanguage', 'sw')
            ->assertSee('Unganisha mashine hii');

        pairTill();

        expect(Preference::read(Preference::LOCALE))->toBe('sw');
        $this->get(route('ready'))->assertSee('Mashine iko tayari');
    });

    it('ignores a language the till does not have', function () {
        Livewire::test(PairTill::class)->call('useLanguage', 'fr');

        expect(Preference::read(Preference::LOCALE))->toBeNull();
    });

    it('shows the till, branch and what was downloaded', function () {
        pairTill();
        Item::query()->create(['id' => 'i1', 'type' => 'product', 'name' => 'Soda', 'price_cents' => 8000, 'vat_category' => 'standard', 'is_active' => true]);
        Item::query()->create(['id' => 'i2', 'type' => 'product', 'name' => 'Old', 'price_cents' => 100, 'vat_category' => 'standard', 'is_active' => false]);

        Livewire::test(TillReady::class)
            ->assertSee('Danamo Restaurant')
            ->assertSee('Till 1 · Nyali')
            ->assertViewHas('itemCount', 1);
    });

    it('goes back to pairing when a manual sync finds the till removed', function () {
        pairTill();
        Http::fake([TEST_API_URL.'/*' => Http::response([], 401)]);

        Livewire::test(TillReady::class)
            ->call('syncNow')
            ->assertRedirect(route('pair'));
    });
});
