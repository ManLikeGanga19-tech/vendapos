<?php

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
            });
    });
