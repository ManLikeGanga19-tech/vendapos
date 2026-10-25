<?php

declare(strict_types=1);

use App\Models\PinAttempt;
use App\Models\StaffMember;
use App\Security\SecretBox;
use App\Staff\CheckPin;
use App\Staff\PinOutcome;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** A staff member as the till stores them, with the PIN hashed like the server does. */
function tillStaff(string $id = 's1', string $pin = '2468', string $role = 'cashier', array $permissions = ['orders.take', 'payments.take']): StaffMember
{
    return StaffMember::query()->create(SyncStaff::row([
        'id' => $id, 'name' => "Staff {$id}", 'role' => $role, 'permissions' => $permissions,
        'pin_hash' => password_hash($pin, PASSWORD_BCRYPT, ['cost' => 4]), 'pin_length' => strlen($pin),
    ], app(SecretBox::class)));
}

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-10-25T08:00:00Z')));

describe('checking a PIN', function () {
    it('accepts the right PIN', function () {
        expect(app(CheckPin::class)->handle(tillStaff(), '2468')->isAccepted())->toBeTrue();
    });

    it('counts down the tries left on a wrong PIN', function () {
        $staff = tillStaff();

        expect(app(CheckPin::class)->handle($staff, '1111')->triesLeft)->toBe(4)
            ->and(app(CheckPin::class)->handle($staff, '1112')->triesLeft)->toBe(3);
    });

    it('locks the person for 5 minutes after 5 wrong PINs, even against the right one', function () {
        $staff = tillStaff();
        foreach (range(1, 4) as $_) {
            app(CheckPin::class)->handle($staff, '0000');
        }

        $fifth = app(CheckPin::class)->handle($staff, '0000');

        expect($fifth->result)->toBe(PinOutcome::LOCKED)
            ->and($fifth->lockedUntil?->toIso8601String())->toBe('2026-10-25T08:05:00+00:00')
            ->and(app(CheckPin::class)->handle($staff, '2468')->result)->toBe(PinOutcome::LOCKED);
    });

    it('starts a fresh count once the lock runs out', function () {
        $staff = tillStaff();
        foreach (range(1, 5) as $_) {
            app(CheckPin::class)->handle($staff, '0000');
        }
        $this->travel(5)->minutes();
        $this->travel(1)->second();

        expect(app(CheckPin::class)->handle($staff, '0000')->triesLeft)->toBe(4)
            ->and(app(CheckPin::class)->handle($staff, '2468')->isAccepted())->toBeTrue()
            ->and(PinAttempt::query()->find('s1'))->toBeNull();
    });

    it('locks one person without locking anyone else', function () {
        $amina = tillStaff('s1');
        $brian = tillStaff('s2', '1357');
        foreach (range(1, 5) as $_) {
            app(CheckPin::class)->handle($amina, '0000');
        }

        expect(app(CheckPin::class)->handle($brian, '1357')->isAccepted())->toBeTrue();
    });
});

describe('the PIN hashes on disk', function () {
    it('are sealed by the keyring, never stored as a bare hash', function () {
        pairTill();
        Http::fake([TEST_API_URL.'/api/v1/staff' => Http::response(['synced_at' => now()->toIso8601String(), 'staff' => [
            ['id' => 's1', 'name' => 'Amina', 'role' => 'cashier', 'permissions' => [], 'pin_hash' => '$2y$04$abcdefghijklmnopqrstuv', 'pin_length' => 4],
        ]])]);

        app(SyncStaff::class)->handle();

        expect(DB::table('staff')->value('pin_hash_sealed'))->not->toContain('$2y$');
    });

    it('drop the lock of someone removed from the staff list', function () {
        pairTill();
        $staff = tillStaff('gone');
        app(CheckPin::class)->handle($staff, '0000');
        Http::fake([TEST_API_URL.'/api/v1/staff' => Http::response(['synced_at' => now()->toIso8601String(), 'staff' => []])]);

        app(SyncStaff::class)->handle();

        expect(PinAttempt::query()->count())->toBe(0);
    });
});

describe('who is signed in', function () {
    it('remembers who signed in and what they may do', function () {
        $session = app(TillSession::class);
        $session->signIn(tillStaff());

        expect($session->staff()?->id)->toBe('s1')
            ->and($session->can(StaffMember::TAKE_PAYMENTS))->toBeTrue()
            ->and($session->can(StaffMember::VOID_ORDERS))->toBeFalse();
    });

    it('signs out after 60 seconds without a tap', function () {
        $session = app(TillSession::class);
        $session->signIn(tillStaff());

        $this->travel(60)->seconds();
        expect($session->staff())->not->toBeNull();

        $this->travel(1)->second();
        expect($session->staff())->toBeNull()
            ->and($session->can(StaffMember::TAKE_ORDERS))->toBeFalse();
    });

    it('keeps someone signed in while they keep tapping', function () {
        $session = app(TillSession::class);
        $session->signIn(tillStaff());

        foreach (range(1, 3) as $_) {
            $this->travel(50)->seconds();
            $session->touch();
        }

        expect($session->staff())->not->toBeNull();
    });

    it('signs out someone the server removed', function () {
        $session = app(TillSession::class);
        $session->signIn(tillStaff());

        StaffMember::query()->whereKey('s1')->delete();

        expect($session->staff())->toBeNull();
    });
});
