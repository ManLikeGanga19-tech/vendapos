<?php

declare(strict_types=1);

namespace App\Till\Updates;

use Native\Desktop\Drivers\Electron\Updater\Contracts\Updater;

/**
 * Updates from a plain HTTPS folder on our own server (Daniel, T6: the Contabo
 * VPS): it holds latest-linux.yml and the AppImage it names. CI uploads them
 * after the build; the till only ever reads, and holds no key.
 */
final readonly class WebUpdates implements Updater
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private array $config) {}

    /** @return array<string, string> nothing: the till holds no key */
    public function environmentVariables(): array
    {
        return [];
    }

    /** @return array{provider: 'generic', url: mixed, channel: 'latest'} electron-builder's publish options */
    public function builderOptions(): array
    {
        return [
            'provider' => 'generic',
            'url' => $this->config['url'],
            'channel' => 'latest',
        ];
    }
}
