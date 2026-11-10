<?php

use App\Livewire\Profile\Settings;
use App\Models\Profile;
use App\Models\User;
use App\Services\Profile\ProfileService;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function profileUser(array $profile = []): User
{
    $user = User::factory()->create(['password' => 'secret-pass-1', 'locale' => 'en']);
    app(ProfileService::class)->save($user, [
        'sex' => 'male', 'birth_date' => '1990-01-01', 'height_cm' => 178, 'weight_kg' => 90, 'goal_weight_kg' => 80,
        'activity_level' => 'light', 'goal' => 'lose', 'approach' => 'standard_keto', ...$profile,
    ]);

    return $user->fresh();
}

it('recalculates when body data is edited', function () {
    $user = profileUser();
    $before = $user->profile->target_calories;

    $this->actingAs($user);
    Livewire::test(Settings::class)->call('editBody')->set('activity_level', 'very_active')->set('weight_kg', 100)->call('saveBody')->assertHasNoErrors();

    $p = $user->fresh()->profile;
    $expected = app(ProfileService::class)->resultFor($p)->roundedTargets();
    expect($p->target_calories)->not->toBe($before)->and($p->target_calories)->toBe($expected->calories);
});

it('shows the live preview while editing', function () {
    $user = profileUser();
    $this->actingAs($user);

    Livewire::test(Settings::class)->call('editBody')->assertSee(__('profile.body.preview_title'))
        ->set('height_cm', 10)->assertSee(__('profile.body.preview_invalid'));
});

it('rejects custom targets below the floor with the localized message', function () {
    $user = profileUser();
    $this->actingAs($user);
    $floor = (int) ceil(app(ProfileService::class)->resultFor($user->profile)->calorieFloor);

    Livewire::test(Settings::class)->call('customise')
        ->set('calories', 900)->call('saveCustomTargets')
        ->assertHasErrors(['calories'])
        ->assertSee(__('macros.validation.calories_below_floor', ['min' => $floor]));

    expect($user->fresh()->profile->targets_customized)->toBeFalse();
});

it('saves custom targets and resets to calculated', function () {
    $user = profileUser();
    $this->actingAs($user);

    Livewire::test(Settings::class)->call('customise')
        ->set('calories', 2000)->set('protein_g', 120)->set('fat_g', 140)->set('net_carbs_g', 20)->set('carbs_g', 35)
        ->call('saveCustomTargets')->assertHasNoErrors();
    expect($user->fresh()->profile->target_calories)->toBe(2000.0)->and($user->fresh()->profile->targets_customized)->toBeTrue();

    Livewire::test(Settings::class)->assertSee(__('profile.targets.customised'))->call('resetTargets');
    $p = $user->fresh()->profile;
    expect($p->targets_customized)->toBeFalse()
        ->and($p->target_calories)->toBe(app(ProfileService::class)->resultFor($p)->roundedTargets()->calories);
});

it('shows the medical notice persistently', function () {
    $user = profileUser();
    $user->profile->update(['needs_medical_supervision' => true]);
    $this->actingAs($user)->get('/profile')->assertSee(__('profile.medical.title', [], 'en'));
});

it('changes the password only with the correct current password', function () {
    $user = profileUser();
    $this->actingAs($user);

    Livewire::test(Settings::class)->set('current_password', 'wrong')->set('password', 'brand-new-pass-9')->set('password_confirmation', 'brand-new-pass-9')
        ->call('updatePassword')->assertHasErrors(['current_password']);

    Livewire::test(Settings::class)->set('current_password', 'secret-pass-1')->set('password', 'brand-new-pass-9')->set('password_confirmation', 'brand-new-pass-9')
        ->call('updatePassword')->assertHasNoErrors();
    expect(Hash::check('brand-new-pass-9', $user->fresh()->password))->toBeTrue();
});

it('persists the locale', function () {
    $user = profileUser();
    $this->actingAs($user);

    Livewire::test(Settings::class)->set('locale', 'ar')->assertRedirect(route('profile'));
    expect($user->fresh()->locale)->toBe('ar');
});

it('updates account details', function () {
    $user = profileUser();
    $this->actingAs($user);

    Livewire::test(Settings::class)->set('name', 'Nour')->set('email', 'nour@example.test')->call('saveAccount')->assertHasNoErrors();
    expect($user->fresh()->name)->toBe('Nour');
});

it('deletes the account after password confirmation', function () {
    $user = profileUser();
    $this->actingAs($user);

    Livewire::test(Settings::class)->set('delete_password', 'nope')->call('deleteAccount')->assertHasErrors(['delete_password']);
    expect(User::find($user->id))->not->toBeNull();

    Livewire::test(Settings::class)->set('delete_password', 'secret-pass-1')->call('deleteAccount')->assertRedirect(route('login'));
    expect(User::find($user->id))->toBeNull()->and(Profile::where('user_id', $user->id)->exists())->toBeFalse();
});

it('renders in arabic rtl and english', function () {
    $user = profileUser();
    $this->actingAs($user)->get('/profile')->assertOk()->assertSee('dir="ltr"', false);
    $user->update(['locale' => 'ar']);
    $this->actingAs($user)->get('/profile')->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('profile.targets.title', [], 'ar'));
});
