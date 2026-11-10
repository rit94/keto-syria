<?php

use App\Livewire\Today;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::livewire('/today', Today\Index::class)->name('today');
});
