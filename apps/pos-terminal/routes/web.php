<?php

declare(strict_types=1);

use App\Livewire\PairTill;
use App\Livewire\TillReady;
use App\Till\Till;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Till $till) => redirect()->route($till->isPaired() ? 'ready' : 'pair'))->name('home');
Route::livewire('/pair', PairTill::class)->name('pair');
Route::livewire('/ready', TillReady::class)->name('ready');
