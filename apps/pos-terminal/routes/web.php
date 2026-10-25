<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureStaffSignedIn;
use App\Livewire\PairTill;
use App\Livewire\SignIn;
use App\Livewire\TillHome;
use App\Staff\TillSession;
use App\Till\Till;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Till $till) => redirect()->route($till->isPaired() ? 'home' : 'pair'))->name('home.entry');
Route::livewire('/pair', PairTill::class)->name('pair');
Route::livewire('/sign-in', SignIn::class)->name('sign-in');

// The idle timer posts here after 60 seconds without a tap.
Route::post('/sign-out', function (TillSession $session) {
    $session->signOut();

    return redirect()->route('sign-in');
})->name('sign-out');

Route::middleware(EnsureStaffSignedIn::class)->group(function () {
    Route::livewire('/home', TillHome::class)->name('home');
});
