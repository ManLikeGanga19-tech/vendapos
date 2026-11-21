<?php

declare(strict_types=1);

use App\Providers\NativeAppServiceProvider;

return [
    // Set by the release build from the tag (terminal-v1.2.3 → 1.2.3); tills compare it to update.
    'version' => env('NATIVEPHP_APP_VERSION', '0.0.0'),
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
    /*
     * Tills update themselves from our Cloudflare R2 bucket (Daniel, T6): CI uploads
     * each build with an S3 key that exists only in CI (AWS_* is stripped from the
     * app), and tills download from the bucket's public address. Off unless the
     * build switches it on, so a developer's till never updates itself.
     */
    'updater' => [
        'enabled' => (bool) env('NATIVEPHP_UPDATER_ENABLED', false),
        'default' => env('NATIVEPHP_UPDATER_PROVIDER', 's3'),
        'providers' => [
            's3' => [
                'driver' => 's3',
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'auto'),
                'bucket' => env('AWS_BUCKET'),
                // R2's S3 API address, for uploading in CI only.
                'endpoint' => env('AWS_ENDPOINT'),
                'path' => env('NATIVEPHP_UPDATER_PATH', 'terminal/linux'),
                // Where tills download from: the bucket's public address plus the path above.
                // Not an AWS_* name, so it survives the build's .env clean-up.
                'public_url' => env('VENDAPOS_UPDATES_URL'),
            ],
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
        // Sync with the server: rounds can wait seconds on a slow connection.
        'default' => [
            'queues' => ['default'],
            'memory_limit' => 128,
            'timeout' => 60,
            'sleep' => 3,
        ],
        // Receipts and bills only, picked up within half a second (App\Jobs\PrintReceiptJob::QUEUE).
        'printing' => [
            'queues' => ['printing'],
            'memory_limit' => 128,
            'timeout' => 30,
            'sleep' => 0.5,
        ],
    ],
    'prebuild' => [],
    'postbuild' => [],
    'nsis' => [
        'delete_app_data_on_uninstall' => false,
    ],
    'binary_path' => env('NATIVEPHP_PHP_BINARY_PATH', null),
];
