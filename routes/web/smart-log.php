<?php

use App\Livewire\SmartLog\Capture;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::livewire('/log', Capture::class)->name('log.smart');
});
