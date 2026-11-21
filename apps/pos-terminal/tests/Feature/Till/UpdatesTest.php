<?php

declare(strict_types=1);

use App\Till\Updates\WebUpdates;
use Native\Desktop\Drivers\Electron\Facades\Updater;

it('builds tills that update from our own HTTPS folder, holding no key', function () {
    config([
        'nativephp.updater.default' => 'web',
        'nativephp.updater.providers.web.url' => 'https://updates.example.co.ke/terminal/linux',
    ]);

    expect(Updater::provider())->toBeInstanceOf(WebUpdates::class)
        ->and(Updater::builderOptions())->toBe(['provider' => 'generic', 'url' => 'https://updates.example.co.ke/terminal/linux', 'channel' => 'latest'])
        ->and(Updater::environmentVariables())->toBe([]);
});

it('never updates a developer’s till', function () {
    expect(config('nativephp.updater.enabled'))->toBeFalse();
});
