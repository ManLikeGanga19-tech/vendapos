<?php

use App\Http\Controllers\RedirectToPreferredLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', RedirectToPreferredLocale::class)->name('root');

Route::prefix('{locale}')
    ->where(['locale' => implode('|', array_keys(config('marketing.locales')))])
    ->middleware(SetLocale::class)
    ->group(function () {
        Route::view('/', 'pages.home')->name('home');
    });
