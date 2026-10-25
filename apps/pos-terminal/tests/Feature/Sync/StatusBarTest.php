<?php

declare(strict_types=1);

use App\Api\Exceptions\ApiUnavailable;
use App\Livewire\PairTill;
use App\Livewire\StatusBar;
use App\Models\Preference;
use App\Models\SyncState;
use App\Sync\SyncStatus;
use App\Sync\SyncTill;
use App\Till\Till;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-10-25T08:00:00Z')));

it('has not synced before the first round', function () {
    expect(SyncStatus::current()->connection)->toBe(SyncStatus::NEVER);
});

it('is online after a recent successful round', function () {
    SyncState::write(SyncTill::LAST_SUCCESS, now()->subSeconds(30)->toIso8601String());

    expect(SyncStatus::current()->connection)->toBe(SyncStatus::ONLINE);
});

it('is offline when the last round could not reach the server', function () {
    SyncState::write(SyncTill::LAST_SUCCESS, now()->subSeconds(30)->toIso8601String());
    SyncState::write(SyncTill::LAST_ERROR, ApiUnavailable::class);

    expect(SyncStatus::current()->connection)->toBe(SyncStatus::OFFLINE);
});

it('is offline when no round has succeeded for three intervals', function () {
    SyncState::write(SyncTill::LAST_SUCCESS, now()->subMinutes(4)->toIso8601String());

    expect(SyncStatus::current()->connection)->toBe(SyncStatus::OFFLINE);
});

it('shows the till, the connection and the last sync in Nairobi time', function () {
    pairTill();
    SyncState::write(SyncTill::LAST_SUCCESS, now()->toIso8601String());

    Livewire::test(StatusBar::class)
        ->assertSee('Till 1 · Nyali')
        ->assertSee(__('terminal.status.online'))
        ->assertSee('synced 11:00')
        ->assertDontSee(__('terminal.status.suspended'));
});

it('tells the cashier to take cash while the account is suspended', function () {
    pairTill();
    SyncState::write(SyncTill::ACCOUNT_STATUS, 'suspended');

    Livewire::test(StatusBar::class)->assertSee(__('terminal.status.suspended'));
});

it('shows nothing on a till that is not paired', function () {
    Livewire::test(StatusBar::class)->assertDontSee(__('terminal.status.never'));

    pairTill();
    app(Till::class)->markRemoved();

    Livewire::test(StatusBar::class)->assertDontSee('Till 1');
});

it('redraws in the language picked after the page loaded', function () {
    pairTill();
    SyncState::write(SyncTill::LAST_SUCCESS, now()->toIso8601String());
    $bar = Livewire::test(StatusBar::class)->assertSee('Online');

    Preference::write(Preference::LOCALE, 'sw');

    $bar->call('refreshLanguage')->assertSee('Mtandaoni')->assertDontSee('Online');
});

it('is told when the language changes', function () {
    Livewire::test(PairTill::class)
        ->call('useLanguage', 'sw')
        ->assertDispatched('language-changed');
});
