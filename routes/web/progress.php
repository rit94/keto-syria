<?php

use App\Livewire\Progress\Index;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::livewire('/progress', Index::class)->name('progress');
});
