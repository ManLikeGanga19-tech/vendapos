<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Actions\ExportTenantSchema;
use App\Domain\Tenancy\Jobs\ExportTenantData;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantExport;
use App\Filament\Admin\Resources\TenantResource\Pages\TenantExports;
use App\Infrastructure\Exports\StreamCipher;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Storage::fake('exports');
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('platform-admin');
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP'); // has set up an authenticator

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Pilau ya kuku', 'price_cents' => 45_000, 'vat_category' => VatCategory::Standard]));
    [$this->other] = tenantWithBranch();
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** Decrypts an export to a temp file and returns its path. */
function decryptedCopy(TenantExport $export): string
{
    $plain = tempnam(sys_get_temp_dir(), 'export-test-');
    $out = fopen($plain, 'wb');
    app(StreamCipher::class)->decrypt(Storage::disk('exports')->path((string) $export->path), fn (string $chunk) => fwrite($out, $chunk));
    fclose($out);

    return $plain;
}

it('exports the tenant’s schema encrypted, verified and kept five years', function () {
    $this->travelTo('2026-10-24 09:00:00');

    $export = app(ExportTenantSchema::class)->handle($this->tenant, requestedBy: $this->admin->id);

    expect($export->status)->toBe(TenantExport::VERIFIED)
        ->and($export->table_counts)->toMatchArray(['branches' => 1, 'items' => 1])
        ->and($export->expires_at?->toDateString())->toBe('2031-10-24')
        ->and($export->requested_by)->toBe($this->admin->id);
    Storage::disk('exports')->assertExists((string) $export->path);

    // At rest it is ciphertext; decrypted it is the pg_dump archive with the same hash.
    $stored = Storage::disk('exports')->get((string) $export->path);
    expect(substr((string) $stored, 0, 5))->not->toBe('PGDMP');

    $plain = decryptedCopy($export);
    expect(file_get_contents($plain, length: 5))->toBe('PGDMP')
        ->and(hash_file('sha256', $plain))->toBe($export->sha256);
    unlink($plain);
});

it('holds only this tenant’s schema', function () {
    $export = app(ExportTenantSchema::class)->handle($this->tenant);
    $plain = decryptedCopy($export);

    $toc = Process::run([(string) config('vendapos.exports.pg_restore'), '--list', $plain])->throw()->output();
    unlink($plain);

    $schema = $this->tenant->database()->getName();
    expect($toc)->toContain("TABLE DATA {$schema} items")
        ->not->toContain($this->other->database()->getName())
        ->not->toContain('TABLE DATA public');
});

it('refuses to decrypt a changed or truncated file, or with another key', function (Closure $spoil) {
    $export = app(ExportTenantSchema::class)->handle($this->tenant);
    $path = Storage::disk('exports')->path((string) $export->path);
    $spoil($path);

    app(StreamCipher::class)->decrypt($path, fn () => null);
})->with([
    'one byte changed' => [function (string $path) {
        $bytes = (string) file_get_contents($path);
        $bytes[100] = $bytes[100] === 'a' ? 'b' : 'a';
        file_put_contents($path, $bytes);
    }],
    'truncated' => [fn (string $path) => file_put_contents($path, substr((string) file_get_contents($path), 0, 200))],
    'another key' => [fn () => config(['vendapos.exports.key' => base64_encode(random_bytes(32))])],
])->throws(RuntimeException::class);

it('records a failed export and leaves no plaintext behind', function () {
    config(['vendapos.exports.pg_dump' => '/nonexistent/pg_dump']);

    expect(fn () => app(ExportTenantSchema::class)->handle($this->tenant))->toThrow(RuntimeException::class);

    expect(TenantExport::query()->sole())->status->toBe(TenantExport::FAILED)->error->not->toBeEmpty()
        ->and(glob(storage_path('app/private/export-work/*')) ?: [])->toBe([]);
    expect(Storage::disk('exports')->allFiles())->toBe([]);
});

it('refuses to export without a valid key', function () {
    config(['vendapos.exports.key' => 'not-a-key']);

    expect(fn () => app(ExportTenantSchema::class)->handle($this->tenant))->toThrow(RuntimeException::class, 'VENDAPOS_EXPORT_KEY');
    expect(TenantExport::query()->sole()->status)->toBe(TenantExport::FAILED);
});

it('lets a platform admin start an export from the tenant’s exports page', function () {
    Queue::fake();
    $this->actingAs($this->admin);

    Livewire::test(TenantExports::class, ['record' => $this->tenant->getRouteKey()])
        ->callTableAction('exportNow')
        ->assertNotified(__('admin.exports.queued'));

    Queue::assertPushed(ExportTenantData::class, fn (ExportTenantData $job) => $job->tenantId === $this->tenant->id
        && $job->connection === 'redis-exports'
        && $job->queue === 'exports'
        && $job->requestedBy === $this->admin->id);
});

it('lists only this tenant’s exports', function () {
    $mine = app(ExportTenantSchema::class)->handle($this->tenant);
    $theirs = app(ExportTenantSchema::class)->handle($this->other);
    $this->actingAs($this->admin);

    Livewire::test(TenantExports::class, ['record' => $this->tenant->getRouteKey()])
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('streams a verified export decrypted to a platform admin and logs it', function () {
    $export = app(ExportTenantSchema::class)->handle($this->tenant);
    Log::shouldReceive('channel')->with('security')->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context) => $message === 'admin.tenant_export_downloaded'
        && $context['export_id'] === $export->id && $context['user_id'] === $this->admin->id);

    $response = $this->actingAs($this->admin)->get(route('filament.admin.tenant-exports.download', ['export' => $export]));

    $response->assertOk()->assertDownload();
    expect(hash('sha256', $response->streamedContent()))->toBe($export->sha256);
});

it('keeps exports from anyone but platform admins', function () {
    $export = app(ExportTenantSchema::class)->handle($this->tenant);
    $someone = User::factory()->create();

    $this->get(route('filament.admin.tenant-exports.download', ['export' => $export]))->assertRedirect();
    $this->actingAs($someone)->get(route('filament.admin.tenant-exports.download', ['export' => $export]))->assertForbidden();

    // A platform admin who has not set up an authenticator is sent to set one up first.
    $noAuthenticator = User::factory()->create();
    $noAuthenticator->assignRole('platform-admin');
    $this->actingAs($noAuthenticator)->get(route('filament.admin.tenant-exports.download', ['export' => $export]))
        ->assertRedirectContains('multi-factor-authentication/set-up');
});

it('runs one export per tenant at a time and skips a purged tenant', function () {
    $job = new ExportTenantData($this->tenant->id);
    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe($this->tenant->id);

    $this->tenant->forceFill(['status' => Tenant::PURGED])->save();
    $job->handle(app(ExportTenantSchema::class));

    expect(TenantExport::query()->count())->toBe(0);
});
