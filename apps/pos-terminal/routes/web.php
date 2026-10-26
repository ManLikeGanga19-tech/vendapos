<?php

declare(strict_types=1);

use App\Http\Controllers\AuthoriseRealtimeChannel;
use App\Http\Middleware\EnsureStaffSignedIn;
use App\Livewire\ChangePin;
use App\Livewire\PairTill;
use App\Livewire\Sale;
use App\Livewire\SignIn;
use App\Staff\TillSession;
use App\Till\Till;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Till $till) => redirect()->route($till->isPaired() ? 'home' : 'pair'))->name('home.entry');
Route::livewire('/pair', PairTill::class)->name('pair');
Route::livewire('/sign-in', SignIn::class)->name('sign-in');

// The window's Echo client signs its private-channel subscription here (see resources/js/realtime.js).
Route::post('/realtime/auth', AuthoriseRealtimeChannel::class)->name('realtime.auth');

// The idle timer posts here after 60 seconds without a tap.
Route::post('/sign-out', function (TillSession $session) {
    $session->signOut();

    return redirect()->route('sign-in');
})->name('sign-out');

Route::middleware(EnsureStaffSignedIn::class)->group(function () {
    Route::livewire('/home', Sale::class)->name('home');
    Route::livewire('/pin', ChangePin::class)->name('pin');
});
