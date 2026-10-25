<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Etims\Actions\TransmitEtimsDocument;
use App\Domain\Etims\Features\EtimsEnabled;
use App\Domain\Etims\Jobs\SendEtimsDocument;
use App\Domain\Tenancy\Actions\PurgeTenant;
use App\Domain\Tenancy\Actions\ReactivateTenant;
use App\Domain\Tenancy\Actions\SuspendTenant;
use App\Domain\Tenancy\Exceptions\TenantStatusConflict;
use App\Domain\Tenancy\Jobs\PurgeTenantData;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantExport;
use App\Domain\Tenancy\Models\TenantStatusChange;
use App\Filament\Admin\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Admin\Resources\TenantResource\Pages\TenantBranches;
use App\Filament\Admin\Resources\TenantResource\Pages\TenantExports;
use App\Infrastructure\Sms\SmsClient;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Storage::fake('exports');
    config(['vendapos.sms.client' => 'fake', 'mail.default' => 'array', 'vendapos.payments.fake' => true]);
    $this->travelTo('2026-10-24 09:00:00'); // 12:00 in Nairobi
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('platform-admin');

    // The tenant to purge, with data in its schema and in every central table purge clears.
    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->tenant->update(['name' => 'Mama Oliech', 'owner_name' => 'Achieng', 'owner_phone' => '+254712345678']);
    $this->tenant->domains()->create(['domain' => 'mama.localhost']);
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    seedCentralRows($this->tenant, $this->branch->id);

    // A neighbour whose rows must all survive.
    [$this->other, $this->otherBranch] = tenantWithBranch();
    $this->other->domains()->create(['domain' => 'other.localhost']);
    $this->otherTill = enrolFor($this->other, $this->otherBranch);
    seedCentralRows($this->other, $this->otherBranch->id);
});

afterEach(function () {
    tenancy()->end();
    Tenant::query()->where('status', '!=', Tenant::PURGED)->get()->each->delete();
    DB::table('tenants')->where('status', Tenant::PURGED)->delete();
});

/** An order (outbox event), a payment callback route, a webhook receipt and an eTIMS flag for the tenant. */
function seedCentralRows(Tenant $tenant, string $branchId): void
{
    $item = $tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Chai', 'price_cents' => 5_000, 'vat_category' => VatCategory::Standard]));
    $device = Device::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $token = $device->createToken('seed', expiresAt: now()->addHour())->plainTextToken;

    app('auth')->forgetGuards();
    test()->withHeaders(['Authorization' => "Bearer {$token}", 'X-Device-ID' => $device->id])
        ->postJson('/api/v1/orders/sync', ['orders' => [saleOf([[$item, 1]])]])
        ->assertJsonPath('results.0.status', 'accepted');
    tenancy()->end();

    DB::table('payment_callback_routes')->insert(['token_hash' => hash('sha256', $tenant->id), 'tenant_id' => $tenant->id, 'payment_id' => (string) Str::uuid(), 'provider' => 'mpesa_ke', 'created_at' => now()]);
    DB::table('webhook_receipts')->insert(['provider' => 'mpesa_ke', 'tenant_id' => $tenant->id, 'ip' => '196.201.214.200', 'body' => '{"phone":"254712345678"}', 'status' => 'processed', 'received_at' => now()]);
    DB::table('device_pairing_codes')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'branch_id' => $branchId, 'device_name' => 'Spare till', 'code_hash' => hash('sha256', $tenant->id), 'issued_by' => (string) Str::uuid(), 'expires_at' => now()->addMinutes(15), 'created_at' => now()]);
    Feature::for(EtimsEnabled::scope($branchId))->activate(EtimsEnabled::class);
}

function suspendAndExpire(Tenant $tenant): Tenant
{
    app(SuspendTenant::class)->handle($tenant, 'Unpaid', test()->admin);
    test()->travel(15)->days();

    return $tenant->fresh();
}

function schemaExists(Tenant $tenant): bool
{
    return DB::table('information_schema.schemata')->where('schema_name', $tenant->database()->getName())->exists();
}

/** @return array<string, int> */
function centralRowsOf(Tenant $tenant): array
{
    return [
        'devices' => DB::table('devices')->where('tenant_id', $tenant->id)->count(),
        'domains' => DB::table('domains')->where('tenant_id', $tenant->id)->count(),
        'domain_events' => DB::table('domain_events')->where('tenant_id', $tenant->id)->count(),
        'payment_callback_routes' => DB::table('payment_callback_routes')->where('tenant_id', $tenant->id)->count(),
        'webhook_receipts' => DB::table('webhook_receipts')->where('tenant_id', $tenant->id)->count(),
        'device_pairing_codes' => DB::table('device_pairing_codes')->where('tenant_id', $tenant->id)->count(),
    ];
}

it('reminds the owner 7 days and 1 day before deletion, once each', function () {
    app(SuspendTenant::class)->handle($this->tenant, 'Unpaid', $this->admin); // purge date 7 November
    $sms = app(SmsClient::class);
    $remindersOn = fn (string $nairobiNoon) => tap(null, function () use ($nairobiNoon) {
        $this->travelTo(Carbon\Carbon::parse($nairobiNoon, 'Africa/Nairobi'));
        $this->artisan('tenants:suspension-reminders')->assertSuccessful();
    });

    $remindersOn('2026-10-30 09:00'); // 8 days left
    expect($sms->sent())->toHaveCount(1); // only the suspension notice

    $remindersOn('2026-10-31 09:00'); // 7 days left
    $remindersOn('2026-10-31 15:00'); // run again the same day
    expect($sms->sent())->toHaveCount(2)
        ->and($sms->sent()[1]['message'])->toContain('in 7 days, on 7 November 2026');

    $remindersOn('2026-11-06 09:00'); // 1 day left
    expect($sms->sent())->toHaveCount(3)
        ->and($sms->sent()[2]['message'])->toContain('tomorrow, 7 November 2026')
        ->and(TenantStatusChange::query()->where('action', TenantStatusChange::REMINDED)->count())->toBe(2);
});

it('reminds in Swahili and never after a reactivation', function () {
    $this->tenant->update(['locale' => 'sw']);
    app(SuspendTenant::class)->handle($this->tenant, 'Unpaid', $this->admin);
    $this->travelTo(Carbon\Carbon::parse('2026-11-06 09:00', 'Africa/Nairobi'));
    $this->artisan('tenants:suspension-reminders');

    expect(collect(app(SmsClient::class)->sent())->last()['message'])->toContain('kufutwa kesho, tarehe 7 Novemba 2026');

    [$quiet] = tenantWithBranch();
    $quiet->update(['owner_phone' => '+254700000001']);
    app(SuspendTenant::class)->handle($quiet, 'Unpaid', $this->admin);
    app(ReactivateTenant::class)->handle($quiet, $this->admin);
    $this->travel(7)->days();
    $count = count(app(SmsClient::class)->sent());
    $this->artisan('tenants:suspension-reminders');

    expect(app(SmsClient::class)->sent())->toHaveCount($count);
});

it('purges nothing before the date it promised', function () {
    app(SuspendTenant::class)->handle($this->tenant, 'Unpaid', $this->admin);
    $this->travel(13)->days();
    Queue::fake();

    $this->artisan('tenants:purge')->assertSuccessful();

    Queue::assertNotPushed(PurgeTenantData::class);
    expect(fn () => app(PurgeTenant::class)->handle($this->tenant->fresh()))->toThrow(TenantStatusConflict::class)
        ->and(schemaExists($this->tenant))->toBeTrue();
});

it('queues one purge per due tenant on the exports connection', function () {
    suspendAndExpire($this->tenant);
    Queue::fake();

    $this->artisan('tenants:purge')->assertSuccessful();

    Queue::assertPushed(PurgeTenantData::class, 1);
    Queue::assertPushed(PurgeTenantData::class, fn (PurgeTenantData $job) => $job->tenantId === $this->tenant->id && $job->connection === 'redis-exports');
});

it('exports, then deletes the schema and every central row of that tenant only', function () {
    $tenant = suspendAndExpire($this->tenant);
    $otherBefore = centralRowsOf($this->other);

    $export = app(PurgeTenant::class)->handle($tenant);

    expect($export->status)->toBe(TenantExport::VERIFIED)
        ->and($export->reason)->toBe(TenantExport::FOR_PURGE)
        ->and($export->table_counts)->toMatchArray(['orders' => 1, 'items' => 1]);
    Storage::disk('exports')->assertExists((string) $export->path);

    expect(schemaExists($tenant))->toBeFalse()
        ->and(centralRowsOf($tenant))->toBe(['devices' => 0, 'domains' => 0, 'domain_events' => 0, 'payment_callback_routes' => 0, 'webhook_receipts' => 0, 'device_pairing_codes' => 0])
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $this->enrolment->device->id)->count())->toBe(0)
        ->and(DB::table('features')->where('scope', EtimsEnabled::scope($this->branch->id))->count())->toBe(0);

    // The neighbour is untouched.
    expect(schemaExists($this->other))->toBeTrue()
        ->and(centralRowsOf($this->other))->toBe($otherBefore)
        ->and(DB::table('features')->where('scope', EtimsEnabled::scope($this->otherBranch->id))->count())->toBe(1);

    // The tombstone and the audit trail stay.
    expect($tenant->fresh())->status->toBe(Tenant::PURGED)->owner_phone->toBe('+254712345678')
        ->and(TenantStatusChange::query()->where('tenant_id', $tenant->id)->where('action', TenantStatusChange::PURGED)->sole()->details)
        ->toMatchArray(['export_id' => $export->id]);
});

it('tells the owner, and leaves nothing reachable', function () {
    $tenant = suspendAndExpire($this->tenant);
    $export = app(PurgeTenant::class)->handle($tenant);

    expect(collect(app(SmsClient::class)->sent())->last()['message'])
        ->toContain('has been deleted')
        ->toContain($export->expires_at?->timezone('Africa/Nairobi')->format('j F Y'));

    deviceRequest('GET', '/api/v1/device', $this->enrolment->plainTextToken, $this->enrolment->device->id)->assertUnauthorized();
    $this->get('http://mama.localhost/login')->assertNotFound();
});

it('deletes nothing when the export fails', function () {
    $tenant = suspendAndExpire($this->tenant);
    config(['vendapos.exports.pg_dump' => '/nonexistent/pg_dump']);

    expect(fn () => app(PurgeTenant::class)->handle($tenant))->toThrow(RuntimeException::class);

    expect(schemaExists($tenant))->toBeTrue()
        ->and($tenant->fresh()->status)->toBe(Tenant::SUSPENDED)
        ->and(centralRowsOf($tenant)['domain_events'])->toBe(1)
        ->and(TenantExport::query()->sole()->status)->toBe(TenantExport::FAILED);
});

it('keeps domain_events append-only outside a purge, and per tenant inside one', function () {
    expect(fn () => DB::table('domain_events')->where('tenant_id', $this->tenant->id)->delete())->toThrow(QueryException::class, 'append-only');

    expect(fn () => DB::transaction(function () {
        DB::select("SELECT set_config('vendapos.purge_tenant', ?, true)", [$this->tenant->id]);
        DB::table('domain_events')->where('tenant_id', $this->other->id)->delete();
    }))->toThrow(QueryException::class, 'append-only');

    expect(fn () => DB::transaction(function () {
        DB::select("SELECT set_config('vendapos.purge_tenant', ?, true)", [$this->tenant->id]);
        DB::table('domain_events')->where('tenant_id', $this->tenant->id)->update(['event_type' => 'rewritten']);
    }))->toThrow(QueryException::class, 'append-only');

    expect(centralRowsOf($this->tenant)['domain_events'])->toBe(1)
        ->and(centralRowsOf($this->other)['domain_events'])->toBe(1);
});

it('leaves purged tenants out of the every-minute sweeps and queued jobs', function () {
    app(PurgeTenant::class)->handle(suspendAndExpire($this->tenant));

    $this->artisan('etims:retry')->assertSuccessful();
    $this->artisan('payments:reconcile')->assertSuccessful();

    // A job queued before the purge finds nothing to do and does not fail.
    (new SendEtimsDocument($this->tenant->id, (string) Str::uuid()))->handle(app(TransmitEtimsDocument::class));
    expect(tenancy()->initialized)->toBeFalse();
});

it('shows a purged tenant in the Admin panel without its schema pages', function () {
    app(PurgeTenant::class)->handle(suspendAndExpire($this->tenant));
    $this->actingAs($this->admin);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])->assertOk();
    Livewire::test(TenantExports::class, ['record' => $this->tenant->getRouteKey()])->assertOk();
    Livewire::test(TenantBranches::class, ['record' => $this->tenant->getRouteKey()])->assertNotFound();
});

it('schedules reminders in office hours and purges at night, Nairobi time', function () {
    $events = collect(app(Schedule::class)->events());
    $find = fn (string $command) => $events->first(fn ($event) => str_contains((string) $event->command, $command));

    expect($find('tenants:suspension-reminders'))->expression->toBe('0 9 * * *')->timezone->toBe('Africa/Nairobi')
        ->and($find('tenants:purge'))->expression->toBe('0 2 * * *')->timezone->toBe('Africa/Nairobi');
});
