<?php

use App\Models\Profile;
use App\Models\User;
use App\Models\WeightEntry;
use App\Services\Profile\ProfileService;
use App\Services\Profile\WeightService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2026-11-10 12:00:00');
    $this->weights = app(WeightService::class);
});

/** User with a service-calculated profile: 30y male, 175 cm, 85 kg, light, lose, goal 75 kg → 1980 kcal. */
function userWithProfile(?array $customTargets = null): User
{
    $user = User::factory()->create();

    app(ProfileService::class)->save($user, [
        'sex' => 'male',
        'birth_date' => '1996-05-01',
        'height_cm' => 175,
        'weight_kg' => 85,
        'goal_weight_kg' => 75,
        'activity_level' => 'light',
        'goal' => 'lose',
        'approach' => 'standard_keto',
    ], $customTargets);

    return $user;
}

describe('record', function () {
    it('creates a weigh-in', function () {
        $user = userWithProfile();

        $entry = $this->weights->record($user, Carbon::parse('2026-11-08'), 84.26, '  after holiday ');

        expect($entry->exists)->toBeTrue()
            ->and($entry->user_id)->toBe($user->id)
            ->and($entry->measured_on->toDateString())->toBe('2026-11-08')
            ->and($entry->weight_kg)->toBe(84.3)
            ->and($entry->note)->toBe('after holiday');
    });

    it('upserts per day', function () {
        $user = userWithProfile();

        $first = $this->weights->record($user, Carbon::parse('2026-11-09 08:00'), 84.0, 'morning');
        $second = $this->weights->record($user, Carbon::parse('2026-11-09 21:30'), 83.6);

        expect(WeightEntry::where('user_id', $user->id)->count())->toBe(1)
            ->and($second->id)->toBe($first->id)
            ->and($second->fresh()->weight_kg)->toBe(83.6)
            ->and($second->fresh()->note)->toBeNull();
    });

    it('keeps entries of different users apart on the same day', function () {
        $a = userWithProfile();
        $b = userWithProfile();

        $this->weights->record($a, Carbon::today(), 84.0);
        $this->weights->record($b, Carbon::today(), 90.0);

        expect(WeightEntry::count())->toBe(2)
            ->and($a->profile()->first()->weight_kg)->toBe(84.0)
            ->and($b->profile()->first()->weight_kg)->toBe(90.0);
    });

    it('updates the profile weight and recalculates targets when the entry is the latest', function () {
        $user = userWithProfile();
        expect($user->profile()->first()->target_calories)->toBe(1980.0);

        $this->weights->record($user, Carbon::today(), 80.0);

        $profile = $user->profile()->first();
        // 80 kg: BMR 1748.75 ; TDEE 2404.53125 ; −20 % = 1923.625 → 1920
        expect($profile->weight_kg)->toBe(80.0)
            ->and($profile->targets()->toArray())->toBe([
                'calories' => 1920.0, 'protein_g' => 132.0, 'fat_g' => 144.0, 'carbs_g' => 52.0, 'net_carbs_g' => 25.0,
            ]);
    });

    it('does not touch the profile when an older day is back-filled', function () {
        $user = userWithProfile();
        $this->weights->record($user, Carbon::parse('2026-11-09'), 84.0);

        $this->weights->record($user, Carbon::parse('2026-11-01'), 88.0);

        expect($user->profile()->first()->weight_kg)->toBe(84.0);
    });

    it('follows a correction of the latest day', function () {
        $user = userWithProfile();
        $this->weights->record($user, Carbon::parse('2026-11-01'), 86.0);
        $this->weights->record($user, Carbon::parse('2026-11-09'), 84.0);

        $this->weights->record($user, Carbon::parse('2026-11-09'), 83.5);

        expect($user->profile()->first()->weight_kg)->toBe(83.5);
    });

    it('updates the weight but keeps customised targets', function () {
        $user = userWithProfile(['calories' => 2000, 'protein_g' => 130, 'fat_g' => 150, 'net_carbs_g' => 25, 'carbs_g' => 40]);

        $this->weights->record($user, Carbon::today(), 80.0);

        $profile = $user->profile()->first();
        expect($profile->weight_kg)->toBe(80.0)
            ->and($profile->targets_customized)->toBeTrue()
            ->and($profile->target_calories)->toBe(2000.0)
            ->and($profile->target_fat_g)->toBe(150.0);
    });

    it('works for a user who has no profile yet', function () {
        $user = User::factory()->create();

        $entry = $this->weights->record($user, Carbon::today(), 70.0);

        expect($entry->exists)->toBeTrue()->and(Profile::count())->toBe(0);
    });

    it('rejects implausible weights and future dates', function () {
        $user = userWithProfile();

        expect(fn () => $this->weights->record($user, Carbon::today(), 29.9))->toThrow(ValidationException::class)
            ->and(fn () => $this->weights->record($user, Carbon::today(), 300.1))->toThrow(ValidationException::class)
            ->and(fn () => $this->weights->record($user, Carbon::tomorrow(), 80.0))->toThrow(ValidationException::class)
            ->and(WeightEntry::count())->toBe(0)
            ->and($user->profile()->first()->weight_kg)->toBe(85.0);
    });
});

describe('delete', function () {
    it('removes the entry and falls back to the previous weight when it was the latest', function () {
        $user = userWithProfile();
        $this->weights->record($user, Carbon::parse('2026-11-01'), 84.0);
        $latest = $this->weights->record($user, Carbon::parse('2026-11-09'), 80.0);
        expect($user->profile()->first()->target_calories)->toBe(1920.0);

        $this->weights->delete($latest);

        $profile = $user->profile()->first();
        expect(WeightEntry::count())->toBe(1)
            ->and($profile->weight_kg)->toBe(84.0)
            ->and($profile->target_calories)->not->toBe(1920.0);
    });

    it('leaves the profile alone when an older entry or the last remaining one is deleted', function () {
        $user = userWithProfile();
        $old = $this->weights->record($user, Carbon::parse('2026-11-01'), 88.0);
        $latest = $this->weights->record($user, Carbon::parse('2026-11-09'), 84.0);

        $this->weights->delete($old);
        expect($user->profile()->first()->weight_kg)->toBe(84.0);

        $this->weights->delete($latest);
        expect(WeightEntry::count())->toBe(0)
            ->and($user->profile()->first()->weight_kg)->toBe(84.0);
    });
});

describe('trend', function () {
    it('computes start, current, change, remaining, progress and the least-squares weekly rate', function () {
        $user = userWithProfile();                       // goal 75 kg
        $this->weights->record($user, Carbon::parse('2026-10-20'), 85.0);
        $this->weights->record($user, Carbon::parse('2026-10-27'), 84.0);
        $this->weights->record($user, Carbon::parse('2026-11-03'), 83.5);
        $this->weights->record($user, Carbon::parse('2026-11-10'), 82.0);

        $trend = $this->weights->trend($user);

        // x = 0, 7, 14, 21 (mean 10.5) ; y mean 83.625
        // Σ(x−x̄)(y−ȳ) = −14.4375 − 1.3125 − 0.4375 − 17.0625 = −33.25 ; Σ(x−x̄)² = 245
        // slope −0.135714 kg/day × 7 = −0.95 kg/week
        // progress (85 − 82) / (85 − 75) = 30 %
        expect($trend['entries'])->toBe([
            ['date' => '2026-10-20', 'kg' => 85.0],
            ['date' => '2026-10-27', 'kg' => 84.0],
            ['date' => '2026-11-03', 'kg' => 83.5],
            ['date' => '2026-11-10', 'kg' => 82.0],
        ])
            ->and($trend['start_kg'])->toBe(85.0)
            ->and($trend['current_kg'])->toBe(82.0)
            ->and($trend['change_kg'])->toBe(-3.0)
            ->and($trend['goal_kg'])->toBe(75.0)
            ->and($trend['remaining_kg'])->toBe(7.0)
            ->and($trend['weekly_rate_kg'])->toEqualWithDelta(-0.95, 1e-9)
            ->and($trend['progress_pct'])->toBe(30.0);
    });

    it('handles unevenly spaced points', function () {
        $user = userWithProfile();
        $this->weights->record($user, Carbon::parse('2026-11-01'), 80.0);
        $this->weights->record($user, Carbon::parse('2026-11-02'), 80.5);
        $this->weights->record($user, Carbon::parse('2026-11-10'), 79.0);

        // x = 0, 1, 9 (mean 10/3) ; y mean 79.8333
        // Σxy' = (−3.3333)(0.16667) + (−2.3333)(0.66667) + (5.6667)(−0.83333) = −6.83333
        // Σx'² = 11.1111 + 5.4444 + 32.1111 = 48.6667 → slope −0.140411 × 7 = −0.983
        expect($this->weights->trend($user)['weekly_rate_kg'])->toEqualWithDelta(-0.983, 1e-9);
    });

    it('limits entries to the window but measures progress from the first ever weigh-in', function () {
        $user = userWithProfile();
        $this->weights->record($user, Carbon::parse('2026-07-01'), 90.0);     // 132 days ago
        $this->weights->record($user, Carbon::parse('2026-10-11'), 84.0);     // 30 days ago – window edge
        $this->weights->record($user, Carbon::parse('2026-11-10'), 82.0);

        $trend = $this->weights->trend($user, 30);

        // progress (90 − 82) / (90 − 75) = 53.3 % ; slope −2 kg / 30 d × 7 = −0.467
        expect($trend['entries'])->toHaveCount(2)
            ->and($trend['start_kg'])->toBe(84.0)
            ->and($trend['change_kg'])->toBe(-2.0)
            ->and($trend['weekly_rate_kg'])->toEqualWithDelta(-0.467, 1e-9)
            ->and($trend['progress_pct'])->toBe(53.3)
            ->and($this->weights->trend($user, 365)['entries'])->toHaveCount(3);
    });

    it('returns null rate with fewer than two points', function () {
        $user = userWithProfile();
        $this->weights->record($user, Carbon::today(), 84.0);

        $trend = $this->weights->trend($user);

        expect($trend['weekly_rate_kg'])->toBeNull()
            ->and($trend['start_kg'])->toBe(84.0)
            ->and($trend['current_kg'])->toBe(84.0)
            ->and($trend['change_kg'])->toBe(0.0)
            ->and($trend['remaining_kg'])->toBe(9.0)
            ->and($trend['progress_pct'])->toBe(0.0);
    });

    it('returns null progress and remaining without a goal weight', function () {
        $user = userWithProfile();
        $user->profile()->first()->update(['goal_weight_kg' => null]);
        $this->weights->record($user, Carbon::parse('2026-11-03'), 85.0);
        $this->weights->record($user, Carbon::parse('2026-11-10'), 84.0);

        $trend = $this->weights->trend($user);

        expect($trend['goal_kg'])->toBeNull()
            ->and($trend['remaining_kg'])->toBeNull()
            ->and($trend['progress_pct'])->toBeNull()
            ->and($trend['weekly_rate_kg'])->toEqualWithDelta(-1.0, 1e-9);
    });

    it('clamps progress at 100% and remaining at 0 once the goal is passed, and at 0% when moving away', function () {
        $reached = userWithProfile();
        $this->weights->record($reached, Carbon::parse('2026-10-01'), 85.0);
        $this->weights->record($reached, Carbon::parse('2026-11-10'), 74.0);

        $away = userWithProfile();
        $this->weights->record($away, Carbon::parse('2026-10-01'), 85.0);
        $this->weights->record($away, Carbon::parse('2026-11-10'), 87.0);

        expect($this->weights->trend($reached)['progress_pct'])->toBe(100.0)
            ->and($this->weights->trend($reached)['remaining_kg'])->toBe(0.0)
            ->and($this->weights->trend($away)['progress_pct'])->toBe(0.0)
            ->and($this->weights->trend($away)['remaining_kg'])->toBe(12.0);
    });

    it('measures progress towards a higher goal weight too', function () {
        $user = userWithProfile();
        $user->profile()->first()->update(['goal' => 'gain', 'goal_weight_kg' => 90]);
        $this->weights->record($user, Carbon::parse('2026-10-01'), 80.0);
        $this->weights->record($user, Carbon::parse('2026-11-10'), 84.0);

        $trend = $this->weights->trend($user);

        expect($trend['progress_pct'])->toBe(40.0)
            ->and($trend['remaining_kg'])->toBe(6.0)
            ->and($trend['change_kg'])->toBe(4.0);
    });

    it('is empty but well-formed without entries, and never includes other users', function () {
        $user = userWithProfile();
        $other = userWithProfile();
        $this->weights->record($other, Carbon::today(), 99.0);

        expect($this->weights->trend($user))->toBe([
            'entries' => [],
            'start_kg' => null,
            'current_kg' => 85.0,
            'change_kg' => null,
            'goal_kg' => 75.0,
            'remaining_kg' => 10.0,
            'weekly_rate_kg' => null,
            'progress_pct' => null,
        ]);
    });
});

describe('policy', function () {
    it('lets only the owner view, update and delete a weigh-in', function () {
        $owner = userWithProfile();
        $stranger = User::factory()->create();
        $entry = $this->weights->record($owner, Carbon::today(), 84.0);

        expect($owner->can('view', $entry))->toBeTrue()
            ->and($owner->can('update', $entry))->toBeTrue()
            ->and($owner->can('delete', $entry))->toBeTrue()
            ->and($stranger->can('view', $entry))->toBeFalse()
            ->and($stranger->can('update', $entry))->toBeFalse()
            ->and($stranger->can('delete', $entry))->toBeFalse()
            ->and($stranger->can('create', WeightEntry::class))->toBeTrue()
            ->and($stranger->can('viewAny', WeightEntry::class))->toBeTrue();
    });
});
