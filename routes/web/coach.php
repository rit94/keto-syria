<?php

use App\Livewire\Coach\Chat;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::livewire('/coach', Chat::class)->name('coach');
    Route::livewire('/coach/{conversation}', Chat::class)->name('coach.show')->whereNumber('conversation');
});
