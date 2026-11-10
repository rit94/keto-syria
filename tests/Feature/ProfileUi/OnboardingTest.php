<?php

use App\Livewire\Onboarding\Wizard;
use App\Models\Profile;
use App\Models\User;
use App\Services\Profile\ProfileService;
use Livewire\Livewire;

function fillWizard($component, array $over = [])
{
    $d = [
        'sex' => 'male', 'birth_date' => now()->subYears(35)->toDateString(), 'height_cm' => 178, 'weight_kg' => 90,
        'goal_weight_kg' => 80, 'activity_level' => 'light', 'goal' => 'lose', 'approach' => 'standard_keto', ...$over,
    ];
    $component->call('next')
        ->set('sex', $d['sex'])->set('birth_date', $d['birth_date'])->call('next')
        ->set('height_cm', $d['height_cm'])->set('weight_kg', $d['weight_kg'])->set('goal_weight_kg', $d['goal_weight_kg'])->call('next')
        ->set('activity_level', $d['activity_level'])->call('next')
        ->set('goal', $d['goal'])->set('approach', $d['approach'])->call('next')
        ->set('none_apply', true)->call('next');

    return $d;
}

it('validates each step', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Wizard::class)
        ->call('next')->assertSet('step', 2)
        ->call('next')->assertHasErrors(['sex', 'birth_date'])->assertSet('step', 2)
        ->set('sex', 'male')->set('birth_date', now()->subYears(5)->toDateString())->call('next')
        ->assertHasErrors(['birth_date'])
        ->set('birth_date', now()->subYears(30)->toDateString())->call('next')->assertSet('step', 3)
        ->set('height_cm', 50)->set('weight_kg', 80)->call('next')->assertHasErrors(['height_cm'])->assertSet('step', 3)
        ->set('height_cm', 175)->call('next')->assertSet('step', 4)
        ->call('next')->assertHasErrors(['activity_level'])
        ->set('activity_level', 'light')->call('next')->assertSet('step', 5)
        ->call('next')->assertHasErrors(['goal', 'approach'])
        ->set('goal', 'maintain')->set('approach', 'strict_keto')->call('next')->assertSet('step', 6)
        ->call('next')->assertHasErrors(['conditions'])->assertSet('step', 6);
});

it('can go back', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Wizard::class)->call('next')->call('back')->assertSet('step', 1);
});

it('completes onboarding with the service targets', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $c = Livewire::test(Wizard::class);
    $d = fillWizard($c);
    $c->assertSet('step', 7)->assertSee(__('macros.disclaimer'))->call('next')->assertRedirect(route('home'));

    $expected = app(ProfileService::class)->preview($d)->roundedTargets();
    $profile = $user->fresh()->profile;
    expect($profile->target_calories)->toBe($expected->calories)
        ->and($profile->target_net_carbs_g)->toBe($expected->netCarbs)
        ->and($profile->targets_customized)->toBeFalse()
        ->and($profile->needs_medical_supervision)->toBeFalse()
        ->and($user->weightEntries()->count())->toBe(1);
});

it('gives different people different targets', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $this->actingAs($a);
    $c = Livewire::test(Wizard::class);
    fillWizard($c);
    $c->call('next');

    $this->actingAs($b);
    $c = Livewire::test(Wizard::class);
    fillWizard($c, ['sex' => 'female', 'weight_kg' => 65, 'height_cm' => 160, 'goal_weight_kg' => 58, 'activity_level' => 'sedentary', 'approach' => 'strict_keto']);
    $c->call('next');

    expect($a->fresh()->profile->target_calories)->not->toBe($b->fresh()->profile->target_calories)
        ->and($a->fresh()->profile->target_net_carbs_g)->not->toBe($b->fresh()->profile->target_net_carbs_g);
});

it('sets the medical flag from the checklist and shows the advice', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $c = Livewire::test(Wizard::class);
    $c->call('next')->set('sex', 'female')->set('birth_date', now()->subYears(30)->toDateString())->call('next')
        ->set('height_cm', 165)->set('weight_kg', 70)->call('next')
        ->set('activity_level', 'light')->call('next')
        ->set('goal', 'maintain')->set('approach', 'standard_keto')->call('next')
        ->set('conditions', ['kidney_disease'])->assertSee(__('macros.conditions.advice'))->call('next')
        ->assertSet('step', 7)->call('next')->assertRedirect(route('home'));

    expect($user->fresh()->profile->needs_medical_supervision)->toBeTrue();
});

it('saves custom targets and shows service errors on the result step', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $c = Livewire::test(Wizard::class);
    fillWizard($c);
    $c->set('customise', true)->set('calories', 900)->call('next')
        ->assertHasErrors(['calories'])->assertSet('step', 7);
    expect($user->fresh()->profile)->toBeNull();

    $c->set('calories', 2000)->set('protein_g', 120)->set('fat_g', 140)->set('net_carbs_g', 20)->set('carbs_g', 35)->call('next')
        ->assertRedirect(route('home'));
    expect($user->fresh()->profile->targets_customized)->toBeTrue();
});

it('redirects users with a profile away and users without one to onboarding', function () {
    $with = User::factory()->create();
    Profile::factory()->create(['user_id' => $with->id]);
    $this->actingAs($with)->get('/onboarding')->assertRedirect(route('home'));

    $without = User::factory()->create();
    $this->actingAs($without)->get('/profile')->assertRedirect(route('onboarding'));
    $this->actingAs($without)->get('/progress')->assertRedirect(route('onboarding'));
    $this->actingAs($without)->get('/onboarding')->assertOk();
});

it('renders in arabic rtl and english', function () {
    $user = User::factory()->create(['locale' => 'ar']);
    $this->actingAs($user)->get('/onboarding')->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('onboarding.start', [], 'ar'));

    $user->update(['locale' => 'en']);
    $this->actingAs($user)->get('/onboarding')->assertOk()->assertSee('dir="ltr"', false)->assertSee('Get started');
});
