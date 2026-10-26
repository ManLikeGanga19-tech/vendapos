<?php

declare(strict_types=1);

use App\Livewire\Sale;
use App\Livewire\SignIn;
use App\Livewire\StatusBar;
use App\Models\StaffMember;
use App\Security\SecretBox;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function staffWithPin(string $id, string $name, string $role, string $pin): StaffMember
{
    return StaffMember::query()->create(SyncStaff::row([
        'id' => $id, 'name' => $name, 'role' => $role, 'permissions' => ['orders.take'],
        'pin_hash' => password_hash($pin, PASSWORD_BCRYPT, ['cost' => 4]), 'pin_length' => strlen($pin),
    ], app(SecretBox::class)));
}

function typePin(mixed $screen, string $pin): mixed
{
    foreach (str_split($pin) as $digit) {
        $screen->call('press', $digit);
    }

    return $screen;
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-25T08:00:00Z'));
    pairTill();
    staffWithPin('s1', 'Amina', 'cashier', '2468');
    staffWithPin('s2', 'Chebet', 'manager', '135792');
});

it('lists who can sign in, with their role', function () {
    Livewire::test(SignIn::class)
        ->assertSee('Amina')->assertSee('Cashier')
        ->assertSee('Chebet')->assertSee('Manager');
});

it('explains what to do when no one has a PIN yet', function () {
    StaffMember::query()->delete();

    Livewire::test(SignIn::class)->assertSee(__('terminal.sign_in.no_staff'));
});

it('signs in with the right PIN, checked as soon as it is long enough', function () {
    $screen = Livewire::test(SignIn::class)->call('choose', 's1');

    typePin($screen, '246')->assertNoRedirect();
    $screen->call('press', '8')->assertRedirect(route('home'));

    expect(app(TillSession::class)->staff()?->id)->toBe('s1');
});

it('waits for all six digits of a manager PIN', function () {
    $screen = Livewire::test(SignIn::class)->call('choose', 's2');

    typePin($screen, '1357')->assertNoRedirect()->assertSet('message', null);
    typePin($screen, '92')->assertRedirect(route('home'));
});

it('says how many tries are left after a wrong PIN', function () {
    $screen = Livewire::test(SignIn::class)->call('choose', 's1');

    typePin($screen, '1111')
        ->assertSet('pin', '')
        ->assertSee('Wrong PIN. 4 tries left.')
        ->assertNoRedirect();

    expect(app(TillSession::class)->staff())->toBeNull();
});

it('says until when someone is locked, in Nairobi time', function () {
    $screen = Livewire::test(SignIn::class)->call('choose', 's1');

    foreach (range(1, 5) as $_) {
        typePin($screen, '1111');
    }

    $screen->assertSee('Try again at 11:05.');
});

it('ignores anything but a single digit', function () {
    Livewire::test(SignIn::class)
        ->call('choose', 's1')
        ->call('press', 'A')->call('press', '12')
        ->assertSet('pin', '');
});

it('ignores a name that is not on the staff list', function () {
    Livewire::test(SignIn::class)->call('choose', 'someone-else')->assertSet('staffId', null);
});

it('keeps people off the home screen until they sign in', function () {
    $this->get(route('home'))->assertRedirect(route('sign-in'));

    app(TillSession::class)->signIn(StaffMember::query()->findOrFail('s1'));

    $this->get(route('home'))->assertOk()->assertSee('Amina')->assertSee(__('terminal.sale.pay'));
});

it('signs out and goes back to the names', function () {
    app(TillSession::class)->signIn(StaffMember::query()->findOrFail('s1'));

    Livewire::test(Sale::class)->call('signOut')->assertRedirect(route('sign-in'));

    expect(app(TillSession::class)->staff())->toBeNull();
});

it('signs out when the idle timer posts', function () {
    app(TillSession::class)->signIn(StaffMember::query()->findOrFail('s1'));

    $this->post(route('sign-out'))->assertRedirect(route('sign-in'));

    expect(app(TillSession::class)->staff())->toBeNull();
});

it('sends a tap after 60 idle seconds back to sign-in', function () {
    app(TillSession::class)->signIn(StaffMember::query()->findOrFail('s1'));
    $home = Livewire::test(Sale::class);

    $this->travel(61)->seconds();

    $home->call('$refresh')->assertRedirect(route('sign-in'));
});

it('does not count the status bar refreshing as someone using the till', function () {
    app(TillSession::class)->signIn(StaffMember::query()->findOrFail('s1'));
    $bar = Livewire::test(StatusBar::class);

    foreach (range(1, 4) as $_) {
        $this->travel(15)->seconds();
        $bar->call('$refresh');
    }
    $this->travel(1)->second();

    expect(app(TillSession::class)->staff())->toBeNull();
});
