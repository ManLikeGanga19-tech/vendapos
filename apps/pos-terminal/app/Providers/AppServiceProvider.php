<?php

declare(strict_types=1);

namespace App\Providers;

use App\Security\NativeSecretBox;
use App\Security\SecretBox;
use App\Till\Till;
use App\Till\Updates\TillUpdaterManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SecretBox::class, NativeSecretBox::class);
        $this->app->singleton(Till::class);
        // Replaces NativePHP's binding (packages register first) to add the "web" update driver.
        $this->app->bind('nativephp.updater', fn ($app) => new TillUpdaterManager($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
