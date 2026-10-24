<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Actions\EnrolDevice;
use App\Domain\Tenancy\Actions\ReactivateTenant;
use App\Domain\Tenancy\Actions\SuspendTenant;
use App\Domain\Tenancy\Exceptions\TenantStatusConflict;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantStatusChange;
use App\Filament\Admin\Resources\TenantResource\Pages\EditTenant;
use App\Infrastructure\Notifications\NotificationDelivery;
use App\Infrastructure\Sms\SmsClient;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    config(['vendapos.sms.client' => 'fake', 'mail.default' => 'array', 'vendapos.payments.fake' => true]);
    $this->travelTo('2026-10-24 09:00:00'); // 12:00 in Nairobi
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('platform-admin');

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->tenant->update(['name' => 'Mama Oliech', 'owner_name' => 'Achieng', 'owner_phone' => '+254712345678', 'owner_email' => 'achieng@example.com']);
    $this->tenant->domains()->create(['domain' => 'mama.localhost']);
    $this->enrolment = enrolFor($this->tenant, $this->branch);

    [$this->other, $this->otherBranch] = tenantWithBranch();
    $this->other->domains()->create(['domain' => 'other.localhost']);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function suspend(Tenant $tenant, string $reason = 'Unpaid subscription for September'): Tenant
{
    return app(SuspendTenant::class)->handle($tenant, $reason, test()->admin);
}

it('suspends from the Admin panel with a reason, sets the purge date and audits it', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->callAction('suspend', data: ['reason' => 'Unpaid subscription for September'])
        ->assertHasNoActionErrors()
        ->assertNotified(__('admin.suspension.suspended'));

    $tenant = $this->tenant->fresh();
    expect($tenant->status)->toBe(Tenant::SUSPENDED)
        ->and($tenant->suspension_reason)->toBe('Unpaid subscription for September')
        ->and($tenant->suspended_by)->toBe($this->admin->id)
        ->and($tenant->purge_after?->toDateTimeString())->toBe('2026-11-07 09:00:00');

    expect(TenantStatusChange::query()->sole())
        ->action->toBe(TenantStatusChange::SUSPENDED)
        ->actor_id->toBe($this->admin->id)
        ->reason->toBe('Unpaid subscription for September');
});

it('requires a reason to suspend', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->callAction('suspend', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($this->tenant->fresh()->isActive())->toBeTrue();
});

it('tells the owner by SMS and email, in their language, with the deletion date', function (string $locale, string $expected) {
    $this->tenant->update(['locale' => $locale]);

    suspend($this->tenant);

    expect(app(SmsClient::class)->sent()[0])
        ->to->toBe('+254712345678')
        ->message->toContain('Mama Oliech')->toContain($expected);
    expect(NotificationDelivery::query()->where('notification', 'tenant.suspended')->pluck('channel')->sort()->values()->all())
        ->toBe(['mail', 'sms']);
})->with([
    'English' => ['en', 'deleted on 7 November 2026'],
    'Kiswahili' => ['sw', 'kufutwa tarehe 7 Novemba 2026'],
]);

it('refuses to suspend twice or to reactivate an active tenant', function () {
    suspend($this->tenant);

    expect(fn () => suspend($this->tenant))->toThrow(TenantStatusConflict::class)
        ->and(fn () => app(ReactivateTenant::class)->handle($this->other, $this->admin))->toThrow(TenantStatusConflict::class)
        ->and(TenantStatusChange::query()->count())->toBe(1);
});

it('reactivates, clears the purge date, audits it and tells the owner', function () {
    suspend($this->tenant);

    app(ReactivateTenant::class)->handle($this->tenant, $this->admin);

    $tenant = $this->tenant->fresh();
    expect($tenant->isActive())->toBeTrue()
        ->and($tenant->purge_after)->toBeNull()
        ->and($tenant->suspension_reason)->toBeNull()
        ->and(TenantStatusChange::query()->orderBy('created_at')->pluck('action')->all())->toBe(['suspended', 'reactivated'])
        ->and(collect(app(SmsClient::class)->sent())->last()['message'])->toContain('active again');
});

it('shows only the suspended notice on the owner panel, sign-in included', function () {
    suspend($this->tenant);

    foreach (['/login', '/', '/reports/sales', '/reports/top-items'] as $path) {
        $this->get("http://mama.localhost{$path}")
            ->assertForbidden()
            ->assertSee(__('owner.suspended.title'))
            ->assertSee('7 November 2026')
            ->assertDontSee(__('owner.auth.username'));
    }
});

it('shows the notice in the owner’s language', function () {
    $this->tenant->update(['locale' => 'sw']);
    suspend($this->tenant);

    $this->get('http://mama.localhost/login')
        ->assertForbidden()
        ->assertSee('Akaunti hii imesimamishwa')
        ->assertSee('7 Novemba 2026');
});

it('keeps another tenant’s panel and payments working', function () {
    suspend($this->tenant);
    $otherTill = enrolFor($this->other, $this->otherBranch);

    $this->get('http://other.localhost/login')->assertOk()->assertSee(__('owner.auth.username'));
    tenancy()->end(); // as each request ends under Octane

    app('auth')->forgetGuards();
    $this->withHeaders(['Authorization' => 'Bearer '.$otherTill->plainTextToken, 'X-Device-ID' => $otherTill->device->id])
        ->postJson('/api/v1/payments', paymentOf())
        ->assertAccepted();
});

it('keeps the tills syncing but refuses new M-Pesa requests', function () {
    $item = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Chai', 'price_cents' => 5_000, 'vat_category' => VatCategory::Standard]));
    suspend($this->tenant);

    sync([saleOf([[$item, 2]])])->assertOk()->assertJsonPath('results.0.status', 'accepted');

    requestPayment(paymentOf())
        ->assertForbidden()
        ->assertJson(['code' => 'tenant_suspended']);

    deviceRequest('GET', '/api/v1/device', $this->enrolment->plainTextToken, $this->enrolment->device->id)
        ->assertOk()
        ->assertJsonPath('account_status', Tenant::SUSPENDED);

    deviceRequest('POST', '/api/v1/device/token', $this->enrolment->plainTextToken, $this->enrolment->device->id)
        ->assertCreated();
});

it('enrols no new tills while suspended', function () {
    suspend($this->tenant);

    app(EnrolDevice::class)->handle($this->tenant->fresh(), $this->branch->id, 'Till 2');
})->throws(InvalidArgumentException::class, 'no new devices');

it('hides the owner login link while suspended', function () {
    suspend($this->tenant);
    $this->actingAs($this->admin);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->assertActionHidden('ownerLoginLink')
        ->assertActionHidden('suspend')
        ->assertActionVisible('reactivate');
});
