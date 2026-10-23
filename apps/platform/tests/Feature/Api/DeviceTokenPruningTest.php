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

it('prunes device tokens daily, a day after they expire', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains((string) $event->command, 'sanctum:prune-expired'));

    expect($events)->toHaveCount(1)
        ->and((string) $events->first()->command)->toContain('--hours=24')
        ->and($events->first()->expression)->toBe('30 3 * * *')
        ->and($events->first()->timezone)->toBe('Africa/Nairobi');
});

it('deletes only tokens that expired more than a day ago', function () {
    [$tenant, $branch] = tenantWithBranch();
    $device = enrolFor($tenant, $branch)->device;
    $live = $device->tokens()->sole();
    $recent = $device->createToken('recent', expiresAt: now()->subHours(2))->accessToken;
    $old = $device->createToken('old', expiresAt: now()->subHours(30))->accessToken;

    $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

    expect($device->tokens()->pluck('id')->all())
        ->toEqualCanonicalizing([$live->id, $recent->id])
        ->not->toContain($old->id);
});
