<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\IssueOwnerSetupLink;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\StaffSetupLink;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function () {
    [$this->tenant] = tenantWithBranch();
    [$this->other] = tenantWithBranch();
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function issueOwnerLink(Tenant $tenant, string $name = 'Achieng Otieno'): string
{
    return $tenant->run(fn () => app(IssueOwnerSetupLink::class)->handle($name, 'ops@vendapos.test'));
}

it('creates the owner on the first link and stores only a hash of the token', function () {
    $this->freezeSecond();

    $token = issueOwnerLink($this->tenant);

    $this->tenant->run(function () use ($token) {
        $owner = Staff::sole();
        $link = StaffSetupLink::sole();

        expect($owner->name)->toBe('Achieng Otieno')
            ->and($owner->hasRole('owner'))->toBeTrue()
            ->and($owner->username)->toBeNull()
            ->and($owner->password)->toBeNull()
            ->and($link->staff_id)->toBe($owner->id)
            ->and($link->token_hash)->toBe(hash('sha256', $token))
            ->and($link->token_hash)->not->toContain($token)
            ->and($link->issued_by)->toBe('ops@vendapos.test')
            ->and($link->expires_at->equalTo(now()->addHours(48)))->toBeTrue();
    });
});

it('reuses the owner and closes the earlier link when a new one is issued', function () {
    $first = issueOwnerLink($this->tenant);
    $second = issueOwnerLink($this->tenant, 'Someone Else');

    $this->tenant->run(function () use ($first, $second) {
        expect(Staff::count())->toBe(1)
            ->and(Staff::sole()->name)->toBe('Achieng Otieno')
            ->and(StaffSetupLink::open()->pluck('token_hash')->all())->toBe([hash('sha256', $second)])
            ->and(StaffSetupLink::where('token_hash', hash('sha256', $first))->open()->exists())->toBeFalse();
    });
});

it('keeps staff, roles and links inside the tenant’s own schema', function () {
    issueOwnerLink($this->tenant);

    $this->other->run(function () {
        expect(Staff::count())->toBe(0)
            ->and(StaffSetupLink::count())->toBe(0)
            ->and(DB::table('model_has_roles')->count())->toBe(0);
    });

    // Platform admins' roles stay central and untouched.
    expect(DB::table('model_has_roles')->count())->toBe(0);
});

it('does not carry one tenant’s loaded roles into the next tenant', function () {
    issueOwnerLink($this->tenant);
    $ownerId = $this->tenant->run(fn () => Staff::sole()->id);

    // Load tenant A's roles into the long-lived permission registrar…
    $this->tenant->run(fn () => expect(Staff::findOrFail($ownerId)->hasRole('owner'))->toBeTrue());

    // …then ask about the same ID in tenant B, where nobody holds that role.
    $this->other->run(function () use ($ownerId) {
        $impostor = new Staff(['name' => 'Impostor']);
        $impostor->id = $ownerId;
        $impostor->save();

        expect($impostor->fresh()?->hasRole('owner'))->toBeFalse()
            ->and(Staff::role('owner')->count())->toBe(0);
    });
});

it('seeds the four staff roles on the tenant guard', function () {
    $this->tenant->run(function () {
        expect(DB::table('roles')->where('guard_name', 'tenant')->orderBy('name')->pluck('name')->all())
            ->toBe(['cashier', 'manager', 'owner', 'waiter']);
    });
});
