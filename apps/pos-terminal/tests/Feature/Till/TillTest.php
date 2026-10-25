<?php

declare(strict_types=1);

use App\Api\Data\IssuedToken;
use App\Models\Category;
use App\Models\Item;
use App\Models\StaffMember;
use App\Models\SyncState;
use App\Models\TillIdentity;
use App\Till\Till;
use App\Till\TillNotPaired;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function seedMenuAndStaff(): void
{
    Category::query()->create(['id' => 'c1', 'name' => 'Drinks', 'sort_order' => 1]);
    Item::query()->create(['id' => 'i1', 'category_id' => 'c1', 'type' => 'product', 'name' => 'Soda', 'price_cents' => 8000, 'vat_category' => 'standard', 'is_active' => true]);
    StaffMember::query()->create(['id' => 's1', 'name' => 'Amina', 'role' => 'cashier', 'permissions' => ['orders.create'], 'pin_hash' => 'hash', 'pin_length' => 4]);
    SyncState::write('catalogue.synced_at', '2026-10-25T08:00:00+00:00');
}

it('stores the token encrypted, never in plain text', function () {
    pairTill('plain-device-token');

    $stored = DB::table('till_identity')->value('token_ciphertext');

    expect($stored)->not->toContain('plain-device-token')
        ->and(app(Till::class)->credentials())->toBe(['device_id' => TEST_DEVICE_ID, 'token' => 'plain-device-token'])
        ->and(app(Till::class)->isPaired())->toBeTrue();
});

it('wipes the menu, staff and sync cursors of an earlier pairing', function () {
    pairTill();
    seedMenuAndStaff();

    app(Till::class)->pair(pairingResult('second-token', branchId: '0199b1f2-0000-7000-8000-0000000000ff'));

    expect(Category::query()->count())->toBe(0)
        ->and(Item::query()->count())->toBe(0)
        ->and(StaffMember::query()->count())->toBe(0)
        ->and(SyncState::read('catalogue.synced_at'))->toBeNull()
        ->and(TillIdentity::query()->count())->toBe(1)
        ->and(app(Till::class)->identity()?->branch_id)->toBe('0199b1f2-0000-7000-8000-0000000000ff');
});

it('replaces the token after a refresh', function () {
    pairTill('old-token');
    $expiresAt = CarbonImmutable::parse('2026-10-26T06:00:00Z');

    app(Till::class)->storeToken(new IssuedToken('new-token', $expiresAt));

    expect(app(Till::class)->credentials()['token'])->toBe('new-token')
        ->and(app(Till::class)->identity()?->token_expires_at?->equalTo($expiresAt))->toBeTrue();
});

it('keeps everything when the server removes the till', function () {
    pairTill();
    seedMenuAndStaff();

    app(Till::class)->markRemoved();

    expect(app(Till::class)->isRemoved())->toBeTrue()
        ->and(app(Till::class)->isPaired())->toBeFalse()
        ->and(Item::query()->count())->toBe(1)
        ->and(StaffMember::query()->count())->toBe(1);
});

it('keeps the first removal time when told again', function () {
    pairTill();
    $this->travelTo(CarbonImmutable::parse('2026-10-25T08:00:00Z'));
    app(Till::class)->markRemoved();
    $this->travelTo(CarbonImmutable::parse('2026-10-25T09:00:00Z'));
    app(Till::class)->markRemoved();

    expect(app(Till::class)->identity()?->removed_at?->toIso8601String())->toBe('2026-10-25T08:00:00+00:00');
});

it('has no credentials before pairing', function () {
    expect(fn () => app(Till::class)->credentials())->toThrow(TillNotPaired::class);
});
