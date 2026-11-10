<?php

use App\Livewire\Foods;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::livewire('/foods', Foods\Index::class)->name('foods.index');
    Route::livewire('/foods/create', Foods\Form::class)->name('foods.create');
    Route::livewire('/foods/{food}', Foods\Show::class)->name('foods.show')->whereNumber('food');
    Route::livewire('/foods/{food}/edit', Foods\Form::class)->name('foods.edit')->whereNumber('food');
});
