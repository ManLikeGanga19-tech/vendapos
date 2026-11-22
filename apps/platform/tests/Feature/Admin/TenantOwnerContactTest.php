<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Admin\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Admin\Resources\TenantResource\Pages\EditTenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(DatabaseMigrations::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('platform-admin');
    $this->actingAs($admin);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** @return array<string, mixed> the modules and first branch every new business is set up with (T7) */
function firstBranch(): array
{
    return ['modules' => ['general'], 'first_branch_name' => 'Main', 'first_branch_type' => 'general'];
}

it('stores the owner contact in real columns with the phone in E.164', function () {
    Livewire::test(CreateTenant::class)
        ->fillForm([
            'id' => 'tmama-oliech',
            'name' => 'Mama Oliech',
            'owner_name' => 'Achieng Otieno',
            'owner_phone' => '0712 345 678',
            'owner_email' => 'achieng@example.com',
            ...firstBranch(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $row = DB::table('tenants')->where('id', 'tmama-oliech')->first();

    expect($row->name)->toBe('Mama Oliech')
        ->and($row->owner_name)->toBe('Achieng Otieno')
        ->and($row->owner_phone)->toBe('+254712345678')
        ->and($row->owner_email)->toBe('achieng@example.com')
        ->and(json_decode((string) $row->data, true))->not->toHaveKeys(['name', 'owner_phone', 'created_at', 'updated_at']);
});

it('rejects a phone number that cannot be normalised', function () {
    Livewire::test(CreateTenant::class)
        ->fillForm([
            'id' => 'tbad-phone',
            'name' => 'Bad Phone',
            'owner_name' => 'Someone',
            'owner_phone' => '12345',
            'owner_email' => 'someone@example.com',
            ...firstBranch(),
        ])
        ->call('create')
        ->assertHasFormErrors(['owner_phone']);

    expect(Tenant::find('tbad-phone'))->toBeNull();
});

it('requires the owner name and phone', function () {
    Livewire::test(CreateTenant::class)
        ->fillForm(['id' => 'tno-owner', 'name' => 'No Owner'])
        ->call('create')
        ->assertHasFormErrors(['owner_name' => 'required', 'owner_phone' => 'required'])
        ->assertHasNoFormErrors(['owner_email']);

    expect(Tenant::find('tno-owner'))->toBeNull();
});

it('lets an admin add an owner to a tenant created before contacts existed', function () {
    $tenant = Tenant::create(['id' => 'tolder', 'name' => 'Older Kiosk']);

    Livewire::test(EditTenant::class, ['record' => $tenant->id])
        ->assertSchemaStateSet(['name' => 'Older Kiosk', 'owner_phone' => null])
        ->fillForm(['owner_name' => 'Juma', 'owner_phone' => '+255 712 345 678', 'owner_email' => 'juma@example.com', 'modules' => ['shop']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tenant->fresh()?->owner_phone)->toBe('+255712345678');
});

it('stores the owner’s language, English unless chosen', function (?string $locale, string $stored) {
    Livewire::test(CreateTenant::class)
        ->fillForm(array_filter([
            'id' => 'tlugha',
            'name' => 'Lugha Shop',
            'owner_name' => 'Juma Hamisi',
            'owner_phone' => '0722 000 111',
            'locale' => $locale,
        ]) + firstBranch())
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Tenant::findOrFail('tlugha')->locale)->toBe($stored)
        ->and(Tenant::findOrFail('tlugha')->preferredLocale())->toBe($stored);
})->with([
    'not chosen' => [null, 'en'],
    'Kiswahili' => ['sw', 'sw'],
]);

it('refuses a language we do not write in', function () {
    Livewire::test(CreateTenant::class)
        ->fillForm(['id' => 'tfr', 'name' => 'French', 'owner_name' => 'X', 'owner_phone' => '0722 000 112', 'locale' => 'fr', ...firstBranch()])
        ->call('create')
        ->assertHasFormErrors(['locale']);
});
