<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\IssuePairingCode;
use App\Domain\Tenancy\Actions\SuspendTenant;
use App\Domain\Tenancy\Exceptions\StaffChangeRefused;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\DevicePairingCode;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Owner\Pages\Devices;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;

uses(DatabaseMigrations::class);

beforeEach(function () {
    // Each test counts its own attempts.
    config(['vendapos.rate_limits.device_pairing.key_prefix' => 'test:device-pairing:'.Str::uuid().':']);

    [$this->tenant, $this->nyali] = tenantWithBranch();
    $this->tenant->update(['name' => 'Mama Oliech Restaurant']);

    $this->tenant->run(function () {
        $this->westlands = Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Supermarket]);
        $this->owner = Staff::create(['name' => 'Owner', 'username' => 'owner', 'password' => 'correct-horse-9']);
        $this->owner->assignRole('owner');
        $this->manager = Staff::create(['name' => 'Manager', 'username' => 'manager', 'password' => 'correct-horse-9']);
        $this->manager->assignRole('manager');
        $this->manager->branches()->attach($this->nyali->id);
    });
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function issueCode(Tenant $tenant, Staff $staff, string $branchId, string $name = 'Front counter'): string
{
    return $tenant->run(fn () => app(IssuePairingCode::class)->handle($tenant, $staff, $branchId, $name));
}

function pair(string $code): TestResponse
{
    tenancy()->end();

    return test()->postJson('/api/v1/device/pair', ['code' => $code]);
}

it('pairs a till once, and the token it gets works', function () {
    $code = issueCode($this->tenant, $this->owner, $this->westlands->id);
    expect($code)->toMatch('/^['.IssuePairingCode::ALPHABET.']{8}$/');

    // Typed loosely on the till: lower case, a dash and a space.
    $response = pair(strtolower(substr($code, 0, 4)).'- '.substr($code, 4))
        ->assertCreated()
        ->assertJsonPath('device.name', 'Front counter')
        ->assertJsonPath('business.name', 'Mama Oliech Restaurant')
        ->assertJsonPath('branch.id', $this->westlands->id)
        ->assertJsonPath('branch.terminal_mode', 'multi_lane');

    $device = Device::query()->findOrFail($response->json('device.id'));
    expect($device->tenant_id)->toBe($this->tenant->id)
        ->and($device->branch_id)->toBe($this->westlands->id)
        ->and(DevicePairingCode::query()->sole()->device_id)->toBe($device->id)
        ->and(DevicePairingCode::query()->sole()->code_hash)->not->toContain($code);

    deviceRequest('GET', '/api/v1/device', $response->json('token'), $device->id)
        ->assertOk()
        ->assertJsonPath('branch.name', 'Westlands');

    pair($code)->assertUnprocessable()->assertJsonValidationErrors(['code']);
    expect(Device::query()->count())->toBe(1);
});

it('refuses expired codes, made-up codes and suspended businesses alike', function () {
    $expired = issueCode($this->tenant, $this->owner, $this->nyali->id);
    $this->travel(16)->minutes();
    pair($expired)->assertUnprocessable();

    pair('ABCD-EFGH')->assertUnprocessable();

    $code = issueCode($this->tenant, $this->owner, $this->nyali->id);
    app(SuspendTenant::class)->handle($this->tenant, 'Unpaid', User::factory()->create());
    pair($code)->assertUnprocessable();

    expect(Device::query()->count())->toBe(0);
});

it('lets managers pair tills only for their own branches', function () {
    $code = issueCode($this->tenant, $this->manager, $this->nyali->id);
    pair($code)->assertCreated();

    expect(fn () => issueCode($this->tenant, $this->manager, $this->westlands->id))->toThrow(StaffChangeRefused::class);
});

it('stops guessing after a few tries from one address', function () {
    config(['vendapos.rate_limits.device_pairing.per_minute' => 3]);
    $code = issueCode($this->tenant, $this->owner, $this->nyali->id);

    foreach (range(1, 3) as $try) {
        pair("WRONG{$try}AB")->assertUnprocessable();
    }

    // Even the right code waits now.
    pair($code)->assertTooManyRequests()->assertHeader('Retry-After');
    expect(Device::query()->count())->toBe(0);
});

it('rejects unknown fields', function () {
    test()->postJson('/api/v1/device/pair', ['code' => 'ABCD-EFGH', 'tenant_id' => $this->tenant->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant_id']);
});

it('shows a pairing code once in the owner panel, and lists only the person’s own tills', function () {
    $nyaliTill = enrolFor($this->tenant, $this->nyali)->device;
    $westlandsTill = Device::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->westlands->id, 'name' => 'Westlands till']);
    [$other, $otherBranch] = tenantWithBranch();
    $theirTill = enrolFor($other, $otherBranch)->device;

    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    $this->actingAs($this->manager, 'tenant');

    Livewire::test(Devices::class)
        ->assertCanSeeTableRecords([$nyaliTill])
        ->assertCanNotSeeTableRecords([$westlandsTill, $theirTill])
        ->callAction(TestAction::make('pairTill')->table(), ['branch_id' => $this->nyali->id, 'name' => 'Bar till'])
        ->assertActionMounted('showPairingCode')
        ->assertMountedActionModalSee('Code for Bar till');

    expect(DevicePairingCode::query()->where('device_name', 'Bar till')->value('branch_id'))->toBe($this->nyali->id);

    Livewire::test(Devices::class)->callAction(TestAction::make('revoke')->table($nyaliTill));
    expect($nyaliTill->fresh()?->isRevoked())->toBeTrue();

    foreach ([$westlandsTill, $theirTill] as $notTheirs) {
        try {
            Livewire::test(Devices::class)->callAction(TestAction::make('revoke')->table($notTheirs));
        } catch (Throwable) {
            // Not a record of this table; refused either way.
        }
    }

    expect($westlandsTill->fresh()?->isRevoked())->toBeFalse()
        ->and($theirTill->fresh()?->isRevoked())->toBeFalse();
});
