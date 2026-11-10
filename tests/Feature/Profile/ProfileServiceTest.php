<?php

use App\Domain\Macros\MacroResult;
use App\Domain\Macros\MacroWarning;
use App\Enums\ActivityLevel;
use App\Enums\DietApproach;
use App\Enums\Sex;
use App\Enums\WeightGoal;
use App\Models\Profile;
use App\Models\User;
use App\Services\Profile\ProfileService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2026-11-10 12:00:00');
    $this->service = app(ProfileService::class);
});

/** 30y male, 175 cm, 85 kg, light, lose, standard keto → 1978.625 kcal, floor 1798.75. */
function profileData(array $overrides = []): array
{
    return [
        'sex' => 'male',
        'birth_date' => '1996-05-01',
        'height_cm' => 175,
        'weight_kg' => 85,
        'goal_weight_kg' => 75,
        'activity_level' => 'light',
        'goal' => 'lose',
        'approach' => 'standard_keto',
        ...$overrides,
    ];
}

function profileErrors(callable $callback): array
{
    try {
        $callback();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    test()->fail('Expected a ValidationException.');
}

describe('save', function () {
    it('stores body data with calculated, rounded targets', function () {
        $user = User::factory()->create();

        $profile = $this->service->save($user, profileData());

        expect($profile->exists)->toBeTrue()
            ->and($profile->user_id)->toBe($user->id)
            ->and($profile->sex)->toBe(Sex::Male)
            ->and($profile->activity_level)->toBe(ActivityLevel::Light)
            ->and($profile->goal)->toBe(WeightGoal::Lose)
            ->and($profile->approach)->toBe(DietApproach::StandardKeto)
            ->and($profile->birth_date->toDateString())->toBe('1996-05-01')
            ->and($profile->age())->toBe(30)
            ->and($profile->height_cm)->toBe(175.0)
            ->and($profile->weight_kg)->toBe(85.0)
            ->and($profile->goal_weight_kg)->toBe(75.0)
            ->and($profile->targets_customized)->toBeFalse()
            ->and($profile->needs_medical_supervision)->toBeFalse();

        expect($profile->fresh()->targets()->toArray())->toBe([
            'calories' => 1980.0, 'protein_g' => 134.0, 'fat_g' => 149.0, 'carbs_g' => 53.0, 'net_carbs_g' => 25.0,
        ]);
    });

    it('keeps one profile per user and updates it', function () {
        $user = User::factory()->create();

        $this->service->save($user, profileData());
        $profile = $this->service->save($user, profileData(['weight_kg' => 80, 'goal_weight_kg' => null]));

        // BMR 1748.75 ; TDEE 2404.53125 ; −20 % = 1923.625 ; reference 77.421875 ; protein 131.6 ; fat 144.1
        expect(Profile::where('user_id', $user->id)->count())->toBe(1)
            ->and($profile->weight_kg)->toBe(80.0)
            ->and($profile->goal_weight_kg)->toBeNull()
            ->and($profile->targets()->toArray())->toBe([
                'calories' => 1920.0, 'protein_g' => 132.0, 'fat_g' => 144.0, 'carbs_g' => 52.0, 'net_carbs_g' => 25.0,
            ]);
    });

    it('gives different people different targets', function () {
        $a = $this->service->save(User::factory()->create(), profileData());
        $b = $this->service->save(User::factory()->create(), profileData([
            'sex' => 'female', 'birth_date' => '1981-03-15', 'height_cm' => 160, 'weight_kg' => 95,
            'goal_weight_kg' => null, 'activity_level' => 'sedentary', 'approach' => 'strict_keto',
        ]));

        // 45y female → floor case: 1564 kcal rounded UP to 1570
        expect($b->targets()->toArray())->toBe([
            'calories' => 1570.0, 'protein_g' => 115.0, 'fat_g' => 114.0, 'carbs_g' => 42.0, 'net_carbs_g' => 20.0,
        ])->and($a->targets())->not->toEqual($b->targets());
    });

    it('accepts enum instances as well as their values', function () {
        $profile = $this->service->save(User::factory()->create(), profileData([
            'sex' => Sex::Male, 'activity_level' => ActivityLevel::Light,
            'goal' => WeightGoal::Lose, 'approach' => DietApproach::StandardKeto,
        ]));

        expect($profile->target_calories)->toBe(1980.0);
    });

    it('sets the medical flag from the self-declaration or from the calculation', function () {
        $declared = $this->service->save(User::factory()->create(), profileData(['needs_medical_supervision' => true]));
        $healthy = $this->service->save(User::factory()->create(), profileData(['needs_medical_supervision' => false]));
        // 175 cm / 50 kg → BMI 16.3 → calculator requires medical advice
        $underweight = $this->service->save(User::factory()->create(), profileData(['weight_kg' => 50, 'goal_weight_kg' => null]));

        expect($declared->needs_medical_supervision)->toBeTrue()
            ->and($healthy->needs_medical_supervision)->toBeFalse()
            ->and($underweight->needs_medical_supervision)->toBeTrue();
    });

    it('validates age through the birth date (13–100)', function (string $birthDate, bool $valid) {
        $run = fn () => $this->service->save(User::factory()->create(), profileData(['birth_date' => $birthDate]));

        if ($valid) {
            expect($run())->toBeInstanceOf(Profile::class);
        } else {
            expect(profileErrors($run))->toHaveKey('birth_date');
        }
    })->with([
        'turns 13 today' => ['2013-11-10', true],
        'still 12' => ['2013-11-11', false],
        'aged 100' => ['1925-11-11', true],
        'turns 101 today' => ['1925-11-10', false],
        'in the future' => ['2027-01-01', false],
    ]);

    it('validates height, weight and goal weight ranges', function (array $overrides, string $field) {
        $errors = profileErrors(fn () => $this->service->save(User::factory()->create(), profileData($overrides)));

        expect($errors)->toHaveKey($field);
    })->with([
        [['height_cm' => 119.9], 'height_cm'],
        [['height_cm' => 230.1], 'height_cm'],
        [['weight_kg' => 29.9], 'weight_kg'],
        [['weight_kg' => 300.1], 'weight_kg'],
        [['goal_weight_kg' => 29], 'goal_weight_kg'],
        [['goal_weight_kg' => 301], 'goal_weight_kg'],
        [['sex' => 'other'], 'sex'],
        [['activity_level' => 'extreme'], 'activity_level'],
        [['goal' => 'shred'], 'goal'],
        [['approach' => 'carnivore'], 'approach'],
        [['weight_kg' => null], 'weight_kg'],
        [['birth_date' => 'not a date'], 'birth_date'],
    ]);

    it('accepts the range boundaries', function () {
        $profile = $this->service->save(User::factory()->create(), profileData([
            'height_cm' => 230, 'weight_kg' => 300, 'goal_weight_kg' => 30,
        ]));

        expect($profile->height_cm)->toBe(230.0)->and($profile->weight_kg)->toBe(300.0);
    });

    it('does not save anything when validation fails', function () {
        $user = User::factory()->create();

        profileErrors(fn () => $this->service->save($user, profileData(['weight_kg' => 10])));

        expect(Profile::count())->toBe(0);
    });

    it('explains range errors in the current locale', function () {
        app()->setLocale('en');
        $en = profileErrors(fn () => $this->service->preview(profileData(['weight_kg' => 10])));
        app()->setLocale('ar');
        $ar = profileErrors(fn () => $this->service->preview(profileData(['weight_kg' => 10])));

        expect($en['weight_kg'][0])->toBe('Weight must be between 30 and 300 kg.')
            ->and($ar['weight_kg'][0])->toBe('يجب أن يكون الوزن بين 30 و300 كغ.');
    });
});

describe('custom targets', function () {
    it('saves valid custom targets and marks the profile as customised', function () {
        // 130 × 4 + 25 × 4 + 150 × 9 = 1970 kcal vs 2000 → within 10 %
        $profile = $this->service->save(User::factory()->create(), profileData(), [
            'calories' => 2000, 'protein_g' => 130, 'fat_g' => 150, 'net_carbs_g' => 25, 'carbs_g' => 40,
        ]);

        expect($profile->targets_customized)->toBeTrue()
            ->and($profile->fresh()->targets()->toArray())->toBe([
                'calories' => 2000.0, 'protein_g' => 130.0, 'fat_g' => 150.0, 'carbs_g' => 40.0, 'net_carbs_g' => 25.0,
            ]);
    });

    it('rejects calories below the personal safety floor with a clear message', function () {
        app()->setLocale('en');

        // Floor for this person = BMR 1798.75 → minimum 1799
        $errors = profileErrors(fn () => $this->service->save(User::factory()->create(), profileData(), [
            'calories' => 1500, 'protein_g' => 120, 'fat_g' => 100, 'net_carbs_g' => 25, 'carbs_g' => 40,
        ]));

        expect($errors)->toHaveKey('calories')
            ->and($errors['calories'][0])->toContain('1799')
            ->and(Profile::count())->toBe(0);
    });

    it('accepts calories exactly at the safety floor', function () {
        // 1799 kcal: 130 × 4 + 25 × 4 + 131 × 9 = 1799
        $profile = $this->service->save(User::factory()->create(), profileData(), [
            'calories' => 1799, 'protein_g' => 130, 'fat_g' => 131, 'net_carbs_g' => 25, 'carbs_g' => 45,
        ]);

        expect($profile->target_calories)->toBe(1799.0);
    });

    it('uses the absolute minimum as floor for small people', function () {
        app()->setLocale('en');

        // 75y female 150 cm 45 kg: BMR 851.5 → floor 1200
        $data = profileData(['sex' => 'female', 'birth_date' => '1951-01-01', 'height_cm' => 150, 'weight_kg' => 45, 'goal_weight_kg' => null]);

        $errors = profileErrors(fn () => $this->service->save(User::factory()->create(), $data, [
            'calories' => 1000, 'protein_g' => 70, 'fat_g' => 70, 'net_carbs_g' => 20, 'carbs_g' => 30,
        ]));

        expect($errors['calories'][0])->toContain('1200');
    });

    it('rejects total carbs below net carbs', function () {
        $errors = profileErrors(fn () => $this->service->save(User::factory()->create(), profileData(), [
            'calories' => 2000, 'protein_g' => 130, 'fat_g' => 150, 'net_carbs_g' => 25, 'carbs_g' => 20,
        ]));

        expect($errors)->toHaveKey('carbs_g');
    });

    it('rejects macros that do not add up to the calorie target and explains the mismatch', function () {
        app()->setLocale('en');

        // 130 × 4 + 25 × 4 + 80 × 9 = 1340 kcal vs 2000 → 33 % off
        $errors = profileErrors(fn () => $this->service->save(User::factory()->create(), profileData(), [
            'calories' => 2000, 'protein_g' => 130, 'fat_g' => 80, 'net_carbs_g' => 25, 'carbs_g' => 40,
        ]));

        expect($errors)->toHaveKey('calories')
            ->and($errors['calories'][0])->toContain('1340')->toContain('2000')->toContain('10%');
    });

    it('accepts macros at the edge of the ±10% tolerance', function () {
        // 130 × 4 + 25 × 4 + 131 × 9 = 1799 kcal vs 1990 → 9.6 % under
        $profile = $this->service->save(User::factory()->create(), profileData(), [
            'calories' => 1990, 'protein_g' => 130, 'fat_g' => 131, 'net_carbs_g' => 25, 'carbs_g' => 40,
        ]);

        expect($profile->targets_customized)->toBeTrue();
    });

    it('requires every custom target and rejects negatives', function () {
        $missing = profileErrors(fn () => $this->service->save(User::factory()->create(), profileData(), ['calories' => 2000]));
        $negative = profileErrors(fn () => $this->service->save(User::factory()->create(), profileData(), [
            'calories' => 2000, 'protein_g' => -5, 'fat_g' => 150, 'net_carbs_g' => 25, 'carbs_g' => 40,
        ]));

        expect($missing)->toHaveKeys(['protein_g', 'fat_g', 'net_carbs_g', 'carbs_g'])
            ->and($negative)->toHaveKey('protein_g');
    });

    it('returns to calculated targets when saved again without custom targets', function () {
        $user = User::factory()->create();
        $this->service->save($user, profileData(), [
            'calories' => 2000, 'protein_g' => 130, 'fat_g' => 150, 'net_carbs_g' => 25, 'carbs_g' => 40,
        ]);

        $profile = $this->service->save($user, profileData());

        expect($profile->targets_customized)->toBeFalse()
            ->and($profile->target_calories)->toBe(1980.0);
    });
});

describe('recalculate', function () {
    it('refreshes calculated targets from the stored body data', function () {
        $profile = $this->service->save(User::factory()->create(), profileData());

        $profile->update(['weight_kg' => 80]);
        $recalculated = $this->service->recalculate($profile);

        expect($recalculated->fresh()->targets()->toArray())->toBe([
            'calories' => 1920.0, 'protein_g' => 132.0, 'fat_g' => 144.0, 'carbs_g' => 52.0, 'net_carbs_g' => 25.0,
        ]);
    });

    it('leaves customised targets untouched', function () {
        $custom = ['calories' => 2000, 'protein_g' => 130, 'fat_g' => 150, 'net_carbs_g' => 25, 'carbs_g' => 40];
        $profile = $this->service->save(User::factory()->create(), profileData(), $custom);

        $profile->update(['weight_kg' => 80]);
        $recalculated = $this->service->recalculate($profile)->fresh();

        expect($recalculated->targets_customized)->toBeTrue()
            ->and($recalculated->weight_kg)->toBe(80.0)
            ->and($recalculated->targets()->toArray())->toBe([
                'calories' => 2000.0, 'protein_g' => 130.0, 'fat_g' => 150.0, 'carbs_g' => 40.0, 'net_carbs_g' => 25.0,
            ]);
    });

    it('raises the medical flag when the new data requires it, and never clears a declared one', function () {
        $profile = $this->service->save(User::factory()->create(), profileData());
        $profile->update(['weight_kg' => 50]);                       // BMI 16.3
        expect($this->service->recalculate($profile)->fresh()->needs_medical_supervision)->toBeTrue();

        $declared = $this->service->save(User::factory()->create(), profileData(['needs_medical_supervision' => true]));
        expect($this->service->recalculate($declared)->fresh()->needs_medical_supervision)->toBeTrue();
    });

    it('is a no-op for a factory profile that already matches its inputs', function () {
        $profile = $this->service->save(User::factory()->create(), profileData());
        $before = $profile->fresh()->targets();

        expect($this->service->recalculate($profile)->fresh()->targets())->toEqual($before);
    });
});

describe('preview', function () {
    it('returns the full-precision result without saving', function () {
        $result = $this->service->preview(profileData());

        expect($result)->toBeInstanceOf(MacroResult::class)
            ->and($result->bmr)->toEqualWithDelta(1798.75, 1e-6)
            ->and($result->tdee)->toEqualWithDelta(2473.28125, 1e-6)
            ->and($result->targets->calories)->toEqualWithDelta(1978.625, 1e-6)
            ->and($result->warnings)->toBe([])
            ->and(Profile::count())->toBe(0);
    });

    it('carries warnings for the UI', function () {
        $result = $this->service->preview(profileData(['goal_weight_kg' => 55]));   // BMI 17.96, −35 %

        expect($result->warnings)->toEqualCanonicalizing([
            MacroWarning::GOAL_WEIGHT_UNDERWEIGHT,
            MacroWarning::AGGRESSIVE_GOAL,
        ]);

        foreach ($result->warnings as $key) {
            expect(__($key))->not->toBe($key);
        }
    });

    it('validates like save', function () {
        expect(profileErrors(fn () => $this->service->preview(profileData(['height_cm' => 90]))))->toHaveKey('height_cm');
    });

    it('matches what save stores and what resultFor reports', function () {
        $profile = $this->service->save(User::factory()->create(), profileData());

        expect($this->service->resultFor($profile))->toEqual($this->service->preview(profileData()));
    });
});
