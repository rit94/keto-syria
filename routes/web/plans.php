<?php

use App\Livewire\Plans;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::livewire('/plans', Plans\Index::class)->name('plans.index');
    Route::livewire('/plans/create', Plans\Form::class)->name('plans.create');
    Route::livewire('/plans/{plan}', Plans\Show::class)->name('plans.show')->whereNumber('plan');
    Route::livewire('/plans/{plan}/edit', Plans\Form::class)->name('plans.edit')->whereNumber('plan');
});
