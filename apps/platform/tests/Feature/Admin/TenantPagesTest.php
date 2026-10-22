<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Etims\Contracts\EtimsClient;
use App\Domain\Etims\Data\EtimsResult;
use App\Domain\Etims\Features\EtimsEnabled;
use App\Domain\Etims\Models\EtimsSubmission;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Resources\TenantResource\Pages\TenantBranches;
use App\Filament\Resources\TenantResource\Pages\TenantDevices;
use App\Filament\Resources\TenantResource\Pages\TenantEtims;
use App\Filament\Resources\TenantResource\Pages\TenantNegativeStock;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use VendaPOS\Core\Data\EtimsDocumentData;
use VendaPOS\Core\Enums\EtimsStatus;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create(['email' => 'ops@vendapos.test']);
    $this->admin->assignRole('platform-admin');

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->tenant->run(fn () => $this->branch->update(['name' => 'Danamo Nyali']));
    $this->nyama = $this->tenant->run(fn () => Item::create([
        'type' => ItemType::Product, 'name' => 'Nyama choma', 'price_cents' => 185_000, 'vat_category' => VatCategory::Standard,
    ]));

    [$this->other, $this->otherBranch] = tenantWithBranch();
    $this->other->run(fn () => $this->otherBranch->update(['name' => 'Other Westlands']));
    $this->otherDevice = enrolFor($this->other, $this->otherBranch)->device;

    $this->actingAs($this->admin);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('shows only the tenant’s own branches and leaves tenancy off', function () {
    Livewire::test(TenantBranches::class, ['record' => $this->tenant->id])
        ->assertOk()
        ->assertSee('Danamo Nyali')
        ->assertDontSee('Other Westlands');

    expect(tenancy()->initialized)->toBeFalse();
});

it('switches eTIMS on for a branch only once it has a KRA branch ID', function () {
    $page = Livewire::test(TenantBranches::class, ['record' => $this->tenant->id]);

    $page->callAction(TestAction::make('switchEtimsOn')->table($this->branch->id));
    expect(Feature::for(EtimsEnabled::scope($this->branch->id))->active(EtimsEnabled::class))->toBeFalse();

    $page->callAction(TestAction::make('setEtimsBranchId')->table($this->branch->id), ['etims_branch_id' => '00'])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('switchEtimsOn')->table($this->branch->id));

    expect($this->tenant->run(fn () => $this->branch->fresh()?->etims_branch_id))->toBe('00')
        ->and(Feature::for(EtimsEnabled::scope($this->branch->id))->active(EtimsEnabled::class))->toBeTrue()
        ->and(Feature::for(EtimsEnabled::scope($this->otherBranch->id))->active(EtimsEnabled::class))->toBeFalse();

    $page->callAction(TestAction::make('switchEtimsOff')->table($this->branch->id));
    expect(Feature::for(EtimsEnabled::scope($this->branch->id))->active(EtimsEnabled::class))->toBeFalse();
});

it('lists and revokes only the tenant’s own devices', function () {
    $device = $this->enrolment->device;

    Livewire::test(TenantDevices::class, ['record' => $this->tenant->id])
        ->assertCanSeeTableRecords([$device])
        ->assertCanNotSeeTableRecords([$this->otherDevice])
        ->callAction(TestAction::make('revoke')->table($device));

    expect($device->fresh()?->isRevoked())->toBeTrue()
        ->and($device->tokens()->count())->toBe(0)
        ->and($this->otherDevice->fresh()?->isRevoked())->toBeFalse();
});

it('shows times in Africa/Nairobi, though they are stored in UTC', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-10-22T10:00:00Z'));
    $device = enrolFor($this->tenant, $this->branch)->device; // token expires 22:00 UTC

    Livewire::test(TenantDevices::class, ['record' => $this->tenant->id])
        ->assertCanSeeTableRecords([$device])
        ->assertSee('Oct 23, 2026 01:00:00')
        ->assertDontSee('Oct 22, 2026 22:00:00');
});

it('cannot revoke another tenant’s device from this tenant’s page', function () {
    $page = Livewire::test(TenantDevices::class, ['record' => $this->tenant->id]);

    try {
        $page->callAction(TestAction::make('revoke')->table($this->otherDevice));
    } catch (Throwable) {
        // Filament refuses a record outside the table's query; either way it must stay active.
    }

    expect($this->otherDevice->fresh()?->isRevoked())->toBeFalse()
        ->and(Device::query()->whereKey($this->otherDevice->id)->value('revoked_at'))->toBeNull();
});

it('lists failed eTIMS documents and sends one again on "Retry now"', function () {
    config(['vendapos.etims.max_attempts' => 1]);
    $client = new class extends EtimsClient
    {
        public bool $up = false;

        public function submitSale(EtimsDocumentData $document): EtimsResult
        {
            return $this->up ? EtimsResult::accepted(['fdn' => 'FDN-9']) : EtimsResult::rejected('kra_down', 'Service unavailable');
        }

        public function submitCreditNote(EtimsDocumentData $document): EtimsResult
        {
            return $this->submitSale($document);
        }
    };
    app()->instance(EtimsClient::class, $client);
    $this->tenant->run(fn () => $this->branch->update(['etims_branch_id' => '00']));
    Feature::for(EtimsEnabled::scope($this->branch->id))->activate(EtimsEnabled::class);

    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);
    $submissionId = $this->tenant->run(fn () => EtimsSubmission::query()->where('order_id', $sale['uuid'])->value('id'));
    $this->actingAs($this->admin);

    $client->up = true;
    Livewire::test(TenantEtims::class, ['record' => $this->tenant->id])
        ->assertSee('kra_down: Service unavailable')
        ->callAction(TestAction::make('retryNow')->table($submissionId));

    expect($this->tenant->run(fn () => EtimsSubmission::query()->findOrFail($submissionId)->status))->toBe(EtimsStatus::Submitted);
});

it('flags items below zero in the tenant’s branches', function () {
    sync([saleOf([[$this->nyama, 2]])]);
    $this->actingAs($this->admin);

    Livewire::test(TenantNegativeStock::class, ['record' => $this->tenant->id])
        ->assertSee('Nyama choma')
        ->assertSee('-2');
});

it('keeps non-staff out of every tenant page', function (string $page) {
    $user = User::factory()->create();
    $user->assignRole('owner');

    $this->actingAs($user)->get("/admin/tenants/{$this->tenant->id}/{$page}")->assertForbidden();
})->with(['branches', 'devices', 'etims', 'negative-stock']);
