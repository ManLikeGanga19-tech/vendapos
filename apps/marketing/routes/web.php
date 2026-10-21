<?php

use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\RedirectToPreferredLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
| Public pages are stateless: no session, no cookies, cacheable by browsers
| and a CDN. Only routes that handle forms keep the full web middleware.
*/
$stateless = [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
];

Route::get('/', RedirectToPreferredLocale::class)
    ->withoutMiddleware($stateless)
    ->name('root');

Route::prefix('{locale}')
    ->where(['locale' => implode('|', array_keys(config('marketing.locales')))])
    ->middleware(SetLocale::class)
    ->group(function () use ($stateless) {
        Route::withoutMiddleware($stateless)
            ->middleware('cache.headers:public;max_age=300;etag')
            ->group(function () {
                Route::view('/', 'pages.home')->name('home');
                Route::view('product', 'pages.product')->name('product');
                Route::view('pricing', 'pages.pricing')->name('pricing');
            });

        // Form pages keep the session for CSRF protection and validation errors.
        Route::controller(DemoRequestController::class)->group(function () {
            Route::get('demo', 'create')->name('demo');
            Route::post('demo', 'store')->middleware('throttle:demo-requests')->name('demo.store');
            Route::get('demo/thanks', 'thanks')->name('demo.thanks');
        });
    });
