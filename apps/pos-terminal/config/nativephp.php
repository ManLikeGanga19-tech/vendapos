<?php

declare(strict_types=1);

use App\Providers\NativeAppServiceProvider;

return [
    'version' => env('NATIVEPHP_APP_VERSION', '1.0.0'),
    'app_id' => env('NATIVEPHP_APP_ID', 'com.vendapos.terminal'),
    'deeplink_scheme' => env('NATIVEPHP_DEEPLINK_SCHEME'),
    'author' => env('NATIVEPHP_APP_AUTHOR', 'VendaPOS'),
    'copyright' => env('NATIVEPHP_APP_COPYRIGHT', 'Copyright VendaPOS'),
    'description' => env('NATIVEPHP_APP_DESCRIPTION', 'VendaPOS Terminal — offline-capable POS for Kenya/East Africa'),
    'website' => env('NATIVEPHP_APP_WEBSITE'),
    'provider' => NativeAppServiceProvider::class,
    'cleanup_env_keys' => [
        'AWS_*',
        'AZURE_*',
        'GITHUB_*',
        'DO_SPACES_*',
        '*_SECRET',
        'BIFROST_*',
    ],
    'cleanup_exclude_files' => [
        'build',
        'temp',
        'node_modules',
        '*/tests',
    ],
    'updater' => [
        'enabled' => env('NATIVEPHP_UPDATER_ENABLED', true),
        'default' => env('NATIVEPHP_UPDATER_PROVIDER', 'github'),
        'providers' => [
            'github' => [
                'driver' => 'github',
                'repo' => env('GITHUB_REPO'),
                'owner' => env('GITHUB_OWNER'),
                'token' => env('GITHUB_TOKEN'),
                'vPrefixedTagName' => true,
                'private' => false,
                'channel' => 'latest',
                'releaseType' => 'draft',
            ],
        ],
    ],
    'queue_workers' => [
        'default' => [
            'queues' => ['default'],
            'memory_limit' => 128,
            'timeout' => 60,
            'sleep' => 3,
        ],
    ],
    'prebuild' => [],
    'postbuild' => [],
    'nsis' => [
        'delete_app_data_on_uninstall' => false,
    ],
    'binary_path' => env('NATIVEPHP_PHP_BINARY_PATH', null),
];
