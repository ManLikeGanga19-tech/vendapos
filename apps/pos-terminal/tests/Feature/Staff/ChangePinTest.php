<?php

declare(strict_types=1);

use App\Livewire\ChangePin;
use App\Models\PinAttempt;
use App\Models\StaffMember;
use App\Security\SecretBox;
use App\Staff\CheckPin;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const AMINA = '0199b1f2-0000-7000-8000-0000000000a1';

function entryFor(string $pin): array
{
    return ['id' => AMINA, 'name' => 'Amina', 'role' => 'cashier', 'permissions' => ['orders.take'], 'pin_hash' => password_hash($pin, PASSWORD_BCRYPT, ['cost' => 4]), 'pin_length' => 4];
}

function typeDigits(mixed $screen, string $digits): mixed
{
    foreach (str_split($digits) as $digit) {
        $screen->call('press', $digit);
    }

    return $screen;
}

beforeEach(function () {
    pairTill();
    $amina = StaffMember::query()->create(SyncStaff::row(entryFor('2468'), app(SecretBox::class)));
    app(TillSession::class)->signIn($amina);
});

it('changes the PIN: current, new, new again', function () {
    Http::fake([TEST_API_URL.'/api/v1/staff/'.AMINA.'/pin' => Http::response(['staff' => entryFor('4829')])]);

    $screen = Livewire::test(ChangePin::class);
    typeDigits($screen, '2468')->assertSet('step', ChangePin::NEW);
    typeDigits($screen, '4829')->assertSet('step', ChangePin::CONFIRM);
    typeDigits($screen, '4829')->assertRedirect(route('home'));

    Http::assertSent(fn (Request $request) => $request['current_pin'] === '2468' && $request['new_pin'] === '4829');
    $amina = StaffMember::query()->findOrFail(AMINA);
    expect(app(CheckPin::class)->handle($amina, '4829')->isAccepted())->toBeTrue()
        ->and(session('notice'))->toBe(__('terminal.pin_change.done'));
});

it('checks the current PIN on the till first, counting towards the lockout', function () {
    Http::fake();

    $screen = Livewire::test(ChangePin::class);
    typeDigits($screen, '1111');
    typeDigits($screen, '4829');
    typeDigits($screen, '4829')
        ->assertSet('step', ChangePin::CURRENT)
        ->assertSee(__('terminal.pin_change.reasons.wrong_pin'));

    Http::assertNothingSent();
    expect(PinAttempt::query()->find(AMINA)?->failures)->toBe(1);
});

it('asks for the new PIN again when the two do not match', function () {
    Http::fake();

    $screen = Livewire::test(ChangePin::class);
    typeDigits($screen, '2468');
    typeDigits($screen, '4829');
    typeDigits($screen, '4830')
        ->assertSet('step', ChangePin::NEW)
        ->assertSee(__('terminal.pin_change.mismatch'));

    Http::assertNothingSent();
});

it('asks for another new PIN when the server finds it too easy', function () {
    Http::fake([TEST_API_URL.'/*' => Http::response(['message' => 'No.', 'reason' => 'too_easy', 'errors' => ['new_pin' => ['too_easy']]], 422)]);

    $screen = Livewire::test(ChangePin::class);
    typeDigits($screen, '2468');
    typeDigits($screen, '7777');
    typeDigits($screen, '7777')
        ->assertSet('step', ChangePin::NEW)
        ->assertSee(__('terminal.pin_change.reasons.too_easy'));

    expect(app(CheckPin::class)->handle(StaffMember::query()->findOrFail(AMINA), '2468')->isAccepted())->toBeTrue();
});

it('says a PIN change needs the internet', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    $screen = Livewire::test(ChangePin::class);
    typeDigits($screen, '2468');
    typeDigits($screen, '4829');
    typeDigits($screen, '4829')->assertSee(__('terminal.pin_change.reasons.offline'));
});

it('swaps an older token without the PIN ability once, then retries', function () {
    Http::fake([
        TEST_API_URL.'/api/v1/staff/'.AMINA.'/pin' => Http::sequence()
            ->push(['message' => 'Invalid ability provided.'], 403)
            ->push(['staff' => entryFor('4829')]),
        TEST_API_URL.'/api/v1/device/token/renew' => Http::response(['message' => 'still valid'], 409),
        TEST_API_URL.'/api/v1/device/token' => Http::response(['token' => 'token-with-pin-ability', 'expires_at' => now()->addHours(12)->toIso8601String()], 201),
    ]);

    $screen = Livewire::test(ChangePin::class);
    typeDigits($screen, '2468');
    typeDigits($screen, '4829');
    typeDigits($screen, '4829')->assertRedirect(route('home'));

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/pin') && $request->hasHeader('Authorization', 'Bearer token-with-pin-ability'));
});

it('is only for someone signed in', function () {
    app(TillSession::class)->signOut();

    $this->get(route('pin'))->assertRedirect(route('sign-in'));
});
