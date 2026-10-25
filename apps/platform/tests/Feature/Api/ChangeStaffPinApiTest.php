<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\ResetStaffPin;
use App\Domain\Tenancy\Actions\SetStaffActive;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\StaffChange;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\BusinessType;

uses(DatabaseMigrations::class);

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function changePin(string $token, string $deviceId, string $staffId, array $body): TestResponse
{
    app('auth')->forgetGuards();

    return test()->postJson("/api/v1/staff/{$staffId}/pin", $body, ['Authorization' => "Bearer {$token}", 'X-Device-ID' => $deviceId]);
}

/**
 * A business with an owner, a cashier at the till's branch and one elsewhere.
 *
 * @return array{tenant: Tenant, token: string, device_id: string, owner: string, cashier: string, elsewhere: string, pins: array<string, string>}
 */
function pinChangeSetup(): array
{
    [$tenant, $nyali] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $nyali);

    $people = $tenant->run(function () use ($nyali) {
        $westlands = Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Restaurant]);
        $person = function (string $name, string $role, array $branchIds): Staff {
            $staff = Staff::create(['name' => $name]);
            $staff->assignRole($role);
            $staff->branches()->attach($branchIds);

            return $staff;
        };
        $owner = $person('Owner', 'owner', []);
        $cashier = $person('Achieng', 'cashier', [$nyali->id]);
        $elsewhere = $person('Baraka', 'cashier', [$westlands->id]);

        $reset = app(ResetStaffPin::class);

        return [
            'owner' => $owner->id, 'cashier' => $cashier->id, 'elsewhere' => $elsewhere->id,
            'pins' => [
                'owner' => $reset->handle($owner, $owner->id),
                'cashier' => $reset->handle($owner, $cashier->id),
                'elsewhere' => $reset->handle($owner, $elsewhere->id),
            ],
        ];
    });

    return ['tenant' => $tenant, 'token' => $enrolment->plainTextToken, 'device_id' => $enrolment->device->id, ...$people];
}

it('lets a cashier choose their own PIN and answers with their new staff-list entry', function () {
    $s = pinChangeSetup();

    $response = changePin($s['token'], $s['device_id'], $s['cashier'], ['current_pin' => $s['pins']['cashier'], 'new_pin' => '4829'])
        ->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->json('staff.id'))->toBe($s['cashier'])
        ->and($response->json('staff.pin_length'))->toBe(4)
        ->and(password_verify('4829', (string) $response->json('staff.pin_hash')))->toBeTrue();

    $s['tenant']->run(function () use ($s) {
        $cashier = Staff::query()->findOrFail($s['cashier']);
        $change = StaffChange::query()->where('staff_id', $s['cashier'])->latest('id')->firstOrFail();

        expect(Hash::check('4829', (string) $cashier->pin_hash))->toBeTrue()
            ->and(Hash::check($s['pins']['cashier'], (string) $cashier->pin_hash))->toBeFalse()
            ->and($change->action)->toBe(StaffChange::ACTION_PIN_CHANGED)
            ->and($change->changed_by)->toBe($s['cashier'])
            ->and($change->details)->toBe(['device_id' => $s['device_id']]);
    });
});

it('lets an owner change their PIN on any till of the business', function () {
    $s = pinChangeSetup();

    changePin($s['token'], $s['device_id'], $s['owner'], ['current_pin' => $s['pins']['owner'], 'new_pin' => '582914'])
        ->assertOk()
        ->assertJsonPath('staff.pin_length', 6);
});

it('refuses a wrong current PIN', function () {
    $s = pinChangeSetup();

    changePin($s['token'], $s['device_id'], $s['cashier'], ['current_pin' => '9999', 'new_pin' => '4829'])
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'wrong_pin')
        ->assertJsonValidationErrors('current_pin');
});

it('stops guessing after five wrong current PINs, even with the right one', function () {
    $s = pinChangeSetup();
    $wrong = '9999'; // never issued: ResetStaffPin skips repeated digits

    foreach (range(1, 5) as $_) {
        changePin($s['token'], $s['device_id'], $s['cashier'], ['current_pin' => $wrong, 'new_pin' => '4829'])->assertUnprocessable();
    }

    changePin($s['token'], $s['device_id'], $s['cashier'], ['current_pin' => $s['pins']['cashier'], 'new_pin' => '4829'])
        ->assertTooManyRequests()
        ->assertJsonPath('reason', 'too_many_tries');
});

it('refuses a new PIN of the wrong length, an easy one, or the same one', function (string $new, string $reason) {
    $s = pinChangeSetup();
    $new = $new === 'same' ? $s['pins']['cashier'] : $new;

    changePin($s['token'], $s['device_id'], $s['cashier'], ['current_pin' => $s['pins']['cashier'], 'new_pin' => $new])
        ->assertUnprocessable()
        ->assertJsonPath('reason', $reason)
        ->assertJsonValidationErrors('new_pin');
})->with([
    'six digits for a cashier' => ['482913', 'wrong_length'],
    'one digit repeated' => ['7777', 'too_easy'],
    'a straight run' => ['3456', 'too_easy'],
    'the current PIN' => ['same', 'same_pin'],
]);

it('treats someone from another branch, inactive or without a PIN as not there', function () {
    $s = pinChangeSetup();
    $s['tenant']->run(function () use ($s) {
        $owner = Staff::query()->findOrFail($s['owner']);
        app(SetStaffActive::class)->handle($owner, $s['cashier'], false);
    });

    changePin($s['token'], $s['device_id'], $s['elsewhere'], ['current_pin' => $s['pins']['elsewhere'], 'new_pin' => '4829'])->assertNotFound();
    changePin($s['token'], $s['device_id'], $s['cashier'], ['current_pin' => $s['pins']['cashier'], 'new_pin' => '4829'])->assertNotFound();
});

it('never reaches staff of another business (leak test)', function () {
    $victim = pinChangeSetup();

    [$otherTenant, $otherBranch] = tenantWithBranch();
    $otherTill = enrolFor($otherTenant, $otherBranch);

    changePin($otherTill->plainTextToken, $otherTill->device->id, $victim['cashier'], ['current_pin' => $victim['pins']['cashier'], 'new_pin' => '4829'])
        ->assertNotFound();

    $victim['tenant']->run(fn () => expect(Hash::check($victim['pins']['cashier'], (string) Staff::query()->findOrFail($victim['cashier'])->pin_hash))->toBeTrue());
});

it('rejects unknown fields', function () {
    $s = pinChangeSetup();

    changePin($s['token'], $s['device_id'], $s['cashier'], ['current_pin' => $s['pins']['cashier'], 'new_pin' => '4829', 'staff_id' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('staff_id');
});

it('needs a token with the staff:pin ability', function () {
    $s = pinChangeSetup();
    $device = Device::query()->findOrFail($s['device_id']);
    $readOnly = $device->createToken('terminal:old', ['staff:read'], now()->addHour())->plainTextToken;

    changePin($readOnly, $s['device_id'], $s['cashier'], ['current_pin' => $s['pins']['cashier'], 'new_pin' => '4829'])->assertForbidden();
});
