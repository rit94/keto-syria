<?php

use App\Livewire\Recipes;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::livewire('/recipes', Recipes\Index::class)->name('recipes.index');
    Route::livewire('/recipes/create', Recipes\Form::class)->name('recipes.create');
    Route::livewire('/recipes/{recipe}', Recipes\Show::class)->name('recipes.show')->whereNumber('recipe');
    Route::livewire('/recipes/{recipe}/edit', Recipes\Form::class)->name('recipes.edit')->whereNumber('recipe');
});
