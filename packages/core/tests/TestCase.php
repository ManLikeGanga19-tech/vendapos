<?php

declare(strict_types=1);

namespace VendaPOS\Core\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\LaravelData\LaravelDataServiceProvider;
use VendaPOS\Core\CoreServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            LaravelDataServiceProvider::class,
        ];
    }
}
