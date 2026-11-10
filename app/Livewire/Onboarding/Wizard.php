<?php

namespace App\Livewire\Onboarding;

use App\Domain\Macros\MacroCalculator;
use App\Domain\Macros\MacroResult;
use App\Enums\ActivityLevel;
use App\Enums\DietApproach;
use App\Enums\Sex;
use App\Enums\WeightGoal;
use App\Livewire\Profile\Concerns\BodyFields;
use App\Livewire\Profile\Concerns\CustomTargetFields;
use App\Services\Profile\ProfileService;
use App\Services\Profile\WeightService;
use App\View\Format;
use App\View\Toast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * First-run flow: one decision per screen, state kept in the component, nothing saved
 * until the last step. Every number shown comes from ProfileService::preview().
 */
#[Layout('layouts::guest')]
class Wizard extends Component
{
    use BodyFields, CustomTargetFields;

    public const int STEP_WELCOME = 1;

    public const int STEP_ABOUT = 2;

    public const int STEP_BODY = 3;

    public const int STEP_ACTIVITY = 4;

    public const int STEP_GOAL = 5;

    public const int STEP_HEALTH = 6;

    public const int STEP_RESULT = 7;

    /** Fields each step is responsible for (ProfileService error keys). */
    private const array STEP_FIELDS = [
        self::STEP_ABOUT => ['sex', 'birth_date'],
        self::STEP_BODY => ['height_cm', 'weight_kg', 'goal_weight_kg'],
        self::STEP_ACTIVITY => ['activity_level'],
        self::STEP_GOAL => ['goal', 'approach'],
        self::STEP_HEALTH => ['conditions'],
        self::STEP_RESULT => ['calories', 'protein_g', 'fat_g', 'net_carbs_g', 'carbs_g'],
    ];

    public int $step = self::STEP_WELCOME;

    /** @var list<string> ticked condition keys (last segment of macros.conditions.*) */
    public array $conditions = [];

    public bool $none_apply = false;

    public bool $customise = false;

    public function mount()
    {
        if (Auth::user()->profile()->exists()) {
            return $this->redirect(route('home'));
        }
    }

    public function chooseLocale(string $locale): void
    {
        if (! in_array($locale, config('app.available_locales'), true)) {
            return;
        }

        session()->put('locale', $locale);
        Auth::user()->forceFill(['locale' => $locale])->save();

        // A full reload so <html lang dir> follows the new language; nothing has been entered yet.
        $this->redirect(route('onboarding'));
    }

    public function updatedConditions(): void
    {
        $this->conditions = array_values(array_intersect($this->conditionKeys(), $this->conditions));

        if ($this->conditions !== []) {
            $this->none_apply = false;
        }

        $this->resetErrorBag('conditions');
    }

    public function updatedNoneApply(bool $value): void
    {
        if ($value) {
            $this->conditions = [];
        }

        $this->resetErrorBag('conditions');
    }

    public function updatedCustomise(bool $value): void
    {
        $this->resetErrorBag(self::customTargetFieldNames());

        if ($value && ($result = $this->result())) {
            $this->fillCustomTargets($result->roundedTargets());
        }
    }

    public function next(ProfileService $profiles, WeightService $weights): void
    {
        if ($this->step >= self::STEP_RESULT) {
            $this->confirm($profiles, $weights);

            return;
        }

        $this->validateStep($profiles, $this->step);

        if ($this->step + 1 === self::STEP_RESULT) {
            // Everything must be valid before targets can be shown.
            try {
                $profiles->preview($this->bodyData());
            } catch (ValidationException $e) {
                $this->step = $this->stepFor(array_keys($e->errors()));

                throw $e;
            }

            $this->customise = false;
        }

        $this->step++;
    }

    public function back(): void
    {
        $this->resetErrorBag();
        $this->step = max(self::STEP_WELCOME, $this->step - 1);
    }

    public function confirm(ProfileService $profiles, WeightService $weights): void
    {
        $user = Auth::user();

        if ($user->profile()->exists()) {
            $this->redirect(route('home'));

            return;
        }

        if ($this->conditions === [] && ! $this->none_apply) {
            $this->step = self::STEP_HEALTH;

            throw ValidationException::withMessages(['conditions' => __('onboarding.health.required')]);
        }

        try {
            $profile = $profiles->save(
                $user,
                [...$this->bodyData(), 'needs_medical_supervision' => $this->conditions !== []],
                $this->customise ? $this->customTargets() : null,
            );
        } catch (ValidationException $e) {
            $this->step = $this->stepFor(array_keys($e->errors()));

            throw $e;
        }

        // The starting weight is the first point of the progress chart.
        $weights->record($user, Carbon::today(), $profile->weight_kg);

        Toast::success(__('onboarding.done'));

        $this->redirect(route('home'));
    }

    public function render(ProfileService $profiles)
    {
        $result = $this->step === self::STEP_RESULT ? $this->result() : null;

        if ($this->step === self::STEP_RESULT && $result === null) {
            $this->step = self::STEP_ABOUT;
        }

        return view('livewire.onboarding.wizard', [
            'totalSteps' => self::STEP_RESULT,
            'sexOptions' => collect(Sex::cases())->mapWithKeys(fn (Sex $s) => [$s->value => $s->label()])->all(),
            'activityOptions' => array_map(fn (ActivityLevel $level) => [
                'value' => $level->value,
                'label' => $level->label(),
                'text' => __('onboarding.activity.levels.'.$level->value),
            ], ActivityLevel::cases()),
            'goalOptions' => array_map(fn (WeightGoal $goal) => [
                'value' => $goal->value,
                'label' => $goal->label(),
                'text' => __('onboarding.goal.goals.'.$goal->value),
            ], WeightGoal::cases()),
            'approachOptions' => array_map(fn (DietApproach $approach) => [
                'value' => $approach->value,
                'label' => $approach->label(),
                'text' => __('onboarding.goal.approaches.'.$approach->value),
                'badge' => __('onboarding.goal.net_carbs_per_day', ['grams' => self::netCarbRange($approach)]),
            ], DietApproach::cases()),
            'conditionOptions' => collect(MacroCalculator::medicalConditions())
                ->mapWithKeys(fn (string $key) => [str($key)->afterLast('.')->value() => __($key)])
                ->all(),
            'maxBirthDate' => Carbon::today()->subYears(ProfileService::MIN_AGE)->toDateString(),
            'minBirthDate' => Carbon::today()->subYears(ProfileService::MAX_AGE + 1)->addDay()->toDateString(),
            'result' => $result,
            'targets' => $result?->roundedTargets(),
            'macroCalories' => $this->customise ? $this->customMacroCalories() : null,
        ])->title(__('onboarding.title'));
    }

    /** Net-carb level of an approach, straight from the calculator's constants ("20", "25–30", "50"). */
    public static function netCarbRange(DietApproach $approach): string
    {
        $base = Format::number(MacroCalculator::NET_CARBS_G[$approach->value]);

        return $approach === DietApproach::StandardKeto
            ? $base.'–'.Format::number(MacroCalculator::STANDARD_KETO_HIGH_TDEE_NET_CARBS_G)
            : $base;
    }

    private function result(): ?MacroResult
    {
        try {
            return app(ProfileService::class)->preview($this->bodyData());
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * Validate one step with the service's own rules and messages: run the full preview
     * and keep only the errors that belong to this step's fields.
     */
    private function validateStep(ProfileService $profiles, int $step): void
    {
        $this->resetErrorBag();

        if ($step === self::STEP_HEALTH) {
            if ($this->conditions === [] && ! $this->none_apply) {
                throw ValidationException::withMessages(['conditions' => __('onboarding.health.required')]);
            }

            return;
        }

        $fields = self::STEP_FIELDS[$step] ?? [];
        if ($fields === []) {
            return;
        }

        try {
            $profiles->preview($this->bodyData());
        } catch (ValidationException $e) {
            $errors = array_intersect_key($e->errors(), array_flip($fields));

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }
    }

    /** @param list<string> $errorKeys */
    private function stepFor(array $errorKeys): int
    {
        foreach (self::STEP_FIELDS as $step => $fields) {
            if (array_intersect($fields, $errorKeys) !== []) {
                return $step;
            }
        }

        return $this->step;
    }

    /** @return list<string> */
    private function conditionKeys(): array
    {
        return array_map(fn (string $key) => str($key)->afterLast('.')->value(), MacroCalculator::medicalConditions());
    }
}
