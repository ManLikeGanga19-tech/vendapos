<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\ResetStaffPin;
use App\Domain\Tenancy\Actions\SetStaffActive;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use VendaPOS\Core\Enums\BusinessType;

uses(DatabaseMigrations::class);

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('gives a till the people who can sign in on it, with hashed PINs and permissions', function () {
    [$tenant, $nyali] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $nyali);

    $pins = $tenant->run(function () use ($nyali) {
        $westlands = Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Restaurant]);
        $person = function (string $name, string $role, array $branchIds): Staff {
            $staff = Staff::create(['name' => $name]);
            $staff->assignRole($role);
            $staff->branches()->attach($branchIds);

            return $staff;
        };
        $owner = $person('Owner', 'owner', []);
        $cashier = $person('Achieng', 'cashier', [$nyali->id]);
        $waiter = $person('Baraka', 'waiter', [$westlands->id]);
        $person('No PIN yet', 'cashier', [$nyali->id]);
        $leaver = $person('Leaver', 'cashier', [$nyali->id]);

        $reset = app(ResetStaffPin::class);
        $pins = ['owner' => $reset->handle($owner, $owner->id), 'cashier' => $reset->handle($owner, $cashier->id)];
        $reset->handle($owner, $waiter->id);
        $reset->handle($owner, $leaver->id);
        app(SetStaffActive::class)->handle($owner, $leaver->id, false);

        return $pins;
    });

    $response = deviceRequest('GET', '/api/v1/staff', $enrolment->plainTextToken, $enrolment->device->id)
        ->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->json('staff.*.name'))->toBe(['Achieng', 'Owner'])
        ->and($response->json('staff.0'))->toMatchArray([
            'role' => 'cashier',
            'permissions' => ['orders.take', 'payments.take', 'receipts.reprint'],
            'pin_length' => 4,
        ])
        ->and($response->json('staff.1.permissions'))->toContain('orders.void', 'drawer.open')
        ->and($response->json('staff.1.pin_length'))->toBe(6)
        ->and(Hash::check($pins['cashier'], $response->json('staff.0.pin_hash')))->toBeTrue()
        ->and(Hash::check($pins['owner'], $response->json('staff.1.pin_hash')))->toBeTrue()
        ->and($response->getContent())->not->toContain($pins['cashier']);
});

it('never lists another tenant’s staff', function () {
    [$tenant, $branch] = tenantWithBranch();
    [$other, $otherBranch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);

    $other->run(function () use ($otherBranch) {
        $owner = Staff::create(['name' => 'Their owner']);
        $owner->assignRole('owner');
        $cashier = Staff::create(['name' => 'Their cashier']);
        $cashier->assignRole('cashier');
        $cashier->branches()->attach($otherBranch->id);
        app(ResetStaffPin::class)->handle($owner, $owner->id);
        app(ResetStaffPin::class)->handle($owner, $cashier->id);
    });

    deviceRequest('GET', '/api/v1/staff', $enrolment->plainTextToken, $enrolment->device->id)
        ->assertOk()
        ->assertJsonCount(0, 'staff');
});

it('needs the staff:read ability and rejects unknown parameters', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);

    deviceRequest('GET', '/api/v1/staff?branch_id=x', $enrolment->plainTextToken, $enrolment->device->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['branch_id']);

    $narrow = $enrolment->device->createToken('narrow', ['orders:sync'], now()->addHour());
    deviceRequest('GET', '/api/v1/staff', $narrow->plainTextToken, $enrolment->device->id)->assertForbidden();
});
