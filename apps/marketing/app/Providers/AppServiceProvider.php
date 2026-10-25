<?php

namespace App\Providers;

use App\Support\ImageManifest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageManifest::class, fn () => new ImageManifest(public_path('images/manifest.json')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Generous for a real visitor fixing typos, tight for a bot.
        RateLimiter::for('demo-requests', fn (Request $request) => [
            Limit::perMinute(5)->by('demo-minute:'.$request->ip()),
            Limit::perDay(20)->by('demo-day:'.$request->ip()),
        ]);
    }
}
