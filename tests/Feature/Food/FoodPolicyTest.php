<?php

use App\Models\Food;
use App\Models\User;
use App\Policies\FoodPolicy;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->other = User::factory()->create();
    $this->catalogue = Food::factory()->create(['user_id' => null, 'slug' => 'policy-catalogue-food']);
    $this->custom = Food::factory()->create(['user_id' => $this->owner->id, 'slug' => null]);
});

it('is the policy Laravel resolves for foods', function () {
    expect(Gate::getPolicyFor(Food::class))->toBeInstanceOf(FoodPolicy::class);
});

it('lets everyone, including guests, view catalogue foods', function () {
    expect($this->owner->can('view', $this->catalogue))->toBeTrue()
        ->and($this->other->can('view', $this->catalogue))->toBeTrue()
        ->and(Gate::forUser(null)->allows('view', $this->catalogue))->toBeTrue()
        ->and(Gate::forUser(null)->allows('viewAny', Food::class))->toBeTrue();
});

it('lets only the owner view a custom food', function () {
    expect($this->owner->can('view', $this->custom))->toBeTrue()
        ->and($this->other->can('view', $this->custom))->toBeFalse()
        ->and(Gate::forUser(null)->allows('view', $this->custom))->toBeFalse();
});

it('lets only the owner update or delete a custom food', function (string $ability) {
    expect($this->owner->can($ability, $this->custom))->toBeTrue()
        ->and($this->other->can($ability, $this->custom))->toBeFalse()
        ->and(Gate::forUser(null)->allows($ability, $this->custom))->toBeFalse();
})->with(['update', 'delete']);

it('lets nobody update or delete a catalogue food', function (string $ability) {
    expect($this->owner->can($ability, $this->catalogue))->toBeFalse()
        ->and($this->other->can($ability, $this->catalogue))->toBeFalse()
        ->and(Gate::forUser(null)->allows($ability, $this->catalogue))->toBeFalse();
})->with(['update', 'delete']);

it('lets any signed-in user create custom foods', function () {
    expect($this->owner->can('create', Food::class))->toBeTrue()
        ->and(Gate::forUser(null)->allows('create', Food::class))->toBeFalse();
});
