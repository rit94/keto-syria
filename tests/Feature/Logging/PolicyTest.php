<?php

use App\Models\FoodLogEntry;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Policies\FoodLogEntryPolicy;
use App\Policies\MealPlanPolicy;
use App\Policies\RecipePolicy;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();
});

it('auto-discovers the policies by naming convention', function () {
    expect(Gate::getPolicyFor(FoodLogEntry::class))->toBeInstanceOf(FoodLogEntryPolicy::class)
        ->and(Gate::getPolicyFor(Recipe::class))->toBeInstanceOf(RecipePolicy::class)
        ->and(Gate::getPolicyFor(MealPlan::class))->toBeInstanceOf(MealPlanPolicy::class);
});

it('lets only the owner view, update and delete', function (string $type) {
    $model = match ($type) {
        'entry' => FoodLogEntry::factory()->create(['user_id' => $this->owner->id]),
        'recipe' => Recipe::factory()->create(['user_id' => $this->owner->id]),
        'plan' => MealPlan::create(['user_id' => $this->owner->id, 'name' => 'Week', 'starts_on' => '2026-11-10', 'days' => 7]),
    };

    foreach (['view', 'update', 'delete'] as $ability) {
        expect($this->owner->can($ability, $model))->toBeTrue("owner should be able to $ability")
            ->and($this->stranger->can($ability, $model))->toBeFalse("stranger must not be able to $ability");
    }
})->with(['entry', 'recipe', 'plan']);

it('denies through the gate facade for another user', function () {
    $entry = FoodLogEntry::factory()->create(['user_id' => $this->owner->id]);

    expect(Gate::forUser($this->stranger)->denies('delete', $entry))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('delete', $entry))->toBeTrue();
});
