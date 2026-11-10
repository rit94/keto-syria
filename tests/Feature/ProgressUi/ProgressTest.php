<?php

use App\Livewire\Progress\Index;
use App\Models\FoodLogEntry;
use App\Models\User;
use App\Models\WeightEntry;
use App\Services\Profile\NutritionHistory;
use App\Services\Profile\ProfileService;
use App\Services\Profile\WeightService;
use App\View\Format;
use Livewire\Livewire;

function progressUser(): User
{
    $user = User::factory()->create(['locale' => 'en']);
    app(ProfileService::class)->save($user, [
        'sex' => 'male', 'birth_date' => '1990-01-01', 'height_cm' => 178, 'weight_kg' => 90, 'goal_weight_kg' => 80,
        'activity_level' => 'light', 'goal' => 'lose', 'approach' => 'standard_keto',
    ]);

    return $user->fresh();
}

it('adds, upserts and deletes weights', function () {
    $user = progressUser();
    $this->actingAs($user);

    $c = Livewire::test(Index::class)->call('addWeight')->set('weight_kg', 89.4)->call('saveWeight')->assertHasNoErrors();
    expect($user->weightEntries()->count())->toBe(1);

    $c->call('addWeight')->assertSet('weight_kg', 89.4)->set('weight_kg', 88.8)->call('saveWeight');
    expect($user->weightEntries()->count())->toBe(1)->and($user->weightEntries()->first()->weight_kg)->toBe(88.8);

    $id = $user->weightEntries()->first()->id;
    $c->call('confirmDelete', $id)->call('deleteWeight');
    expect($user->weightEntries()->count())->toBe(0);
});

it('shows service validation errors', function () {
    $this->actingAs(progressUser());

    Livewire::test(Index::class)->call('addWeight')->set('weight_kg', 10)->call('saveWeight')->assertHasErrors(['weight_kg'])
        ->set('weight_kg', 80)->set('measured_on', now()->addDay()->toDateString())->call('saveWeight')->assertHasErrors(['measured_on']);
});

it('cannot delete or edit another users entry', function () {
    $owner = progressUser();
    $entry = WeightEntry::create(['user_id' => $owner->id, 'measured_on' => now()->toDateString(), 'weight_kg' => 90]);

    $this->actingAs(progressUser());
    Livewire::test(Index::class)->call('confirmDelete', $entry->id)->assertForbidden();
    Livewire::test(Index::class)->call('editWeight', $entry->id)->assertForbidden();
    expect(WeightEntry::find($entry->id))->not->toBeNull();
});

it('shows trend numbers equal to the service', function () {
    $user = progressUser();
    $weights = app(WeightService::class);
    $weights->record($user, now()->subDays(14), 92.0);
    $weights->record($user, now()->subDays(7), 90.5);
    $weights->record($user, now(), 89.0);
    $this->actingAs($user);

    $trend = $weights->trend($user, 90);
    Livewire::test(Index::class)
        ->assertSee(Format::number($trend['current_kg'], 1))
        ->assertSee(Format::number($trend['remaining_kg'], 1))
        ->assertSee(Format::number(abs($trend['weekly_rate_kg']), 2))
        ->assertSee(Format::number($trend['progress_pct'], 1));
});

it('cautions about fast loss', function () {
    $user = progressUser();
    $weights = app(WeightService::class);
    $weights->record($user, now()->subDays(14), 100.0);
    $weights->record($user, now(), 94.0);
    $this->actingAs($user);

    Livewire::test(Index::class)->assertSee(__('progress.weight.fast_title'));
});

it('computes nutrition history averages through the engine', function () {
    $user = progressUser();
    FoodLogEntry::factory()->create(['user_id' => $user->id, 'logged_on' => now()->toDateString(), 'calories' => 1000, 'carbs_g' => 22, 'fiber_g' => 2]);
    FoodLogEntry::factory()->create(['user_id' => $user->id, 'logged_on' => now()->subDay()->toDateString(), 'calories' => 2000, 'carbs_g' => 52, 'fiber_g' => 2]);
    FoodLogEntry::factory()->create(['user_id' => $user->id, 'logged_on' => now()->subDays(5)->toDateString(), 'calories' => 3000, 'carbs_g' => 12, 'fiber_g' => 2]);

    $h = app(NutritionHistory::class)->forUser($user, 7);

    expect($h['logged_days'])->toBe(3)
        ->and($h['average']->calories)->toBe(2000.0)
        ->and($h['average']->netCarbs())->toEqualWithDelta(80.0 / 3, 1e-9)
        ->and($h['within_net_carbs'])->toBe(2)
        ->and($h['streak'])->toBe(2);
    expect($h['days'])->toHaveCount(7);
});

it('has an empty history and zero streak without logs', function () {
    $h = app(NutritionHistory::class)->forUser(progressUser(), 7);
    expect($h['average'])->toBeNull()->and($h['logged_days'])->toBe(0)->and($h['streak'])->toBe(0);
});

it('renders in arabic rtl and english, with and without data', function () {
    $user = progressUser();
    $this->actingAs($user)->get('/progress')->assertOk()->assertSee('dir="ltr"', false)->assertSee(__('progress.weight.empty_title', [], 'en'));
    $user->update(['locale' => 'ar']);
    $this->actingAs($user)->get('/progress')->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('progress.title', [], 'ar'));
});
