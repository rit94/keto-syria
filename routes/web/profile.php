<?php

use App\Livewire\Onboarding\Wizard;
use App\Livewire\Profile\Settings;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::livewire('/onboarding', Wizard::class)->name('onboarding');
});

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::livewire('/profile', Settings::class)->name('profile');
});
