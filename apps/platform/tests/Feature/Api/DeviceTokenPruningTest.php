<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('prunes device tokens daily, a day after their renewal grace window ends', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains((string) $event->command, 'sanctum:prune-expired'));
    $hours = ((int) config('vendapos.device_token_grace_days') + 1) * 24;

    expect($events)->toHaveCount(1)
        ->and($hours)->toBe(192)
        ->and((string) $events->first()->command)->toContain("--hours={$hours}")
        ->and($events->first()->expression)->toBe('30 3 * * *')
        ->and($events->first()->timezone)->toBe('Africa/Nairobi');
});

it('keeps tokens that can still be renewed', function () {
    [$tenant, $branch] = tenantWithBranch();
    $device = enrolFor($tenant, $branch)->device;
    $live = $device->tokens()->sole();
    $renewable = $device->createToken('renewable', expiresAt: now()->subDays(6))->accessToken;
    $old = $device->createToken('old', expiresAt: now()->subDays(9))->accessToken;

    $this->artisan('sanctum:prune-expired', ['--hours' => 192])->assertSuccessful();

    expect($device->tokens()->pluck('id')->all())
        ->toEqualCanonicalizing([$live->id, $renewable->id])
        ->not->toContain($old->id);
});
