<?php

namespace App\Livewire\Profile;

use App\Domain\Macros\MacroResult;
use App\Enums\ActivityLevel;
use App\Enums\DietApproach;
use App\Enums\Sex;
use App\Enums\WeightGoal;
use App\Livewire\Concerns\Toasts;
use App\Livewire\Onboarding\Wizard;
use App\Livewire\Profile\Concerns\BodyFields;
use App\Livewire\Profile\Concerns\CustomTargetFields;
use App\Models\Profile;
use App\Models\User;
use App\Services\Profile\ProfileService;
use App\Services\Profile\WeightService;
use App\View\Toast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Profile page: account, body data + goal, daily targets (calculated or customised).
 * Every target number is read from the profile or from ProfileService — never computed here.
 */
class Settings extends Component
{
    use BodyFields, CustomTargetFields, Toasts;

    // Account
    public string $name = '';

    public string $email = '';

    public string $locale = 'ar';

    // Password change
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Section state
    public bool $editingBody = false;

    public bool $customising = false;

    // Delete account
    public string $delete_password = '';

    public function mount(): void
    {
        $user = $this->user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->locale = app()->getLocale();

        $this->fillBodyFields($this->profile());
    }

    // ── Account ────────────────────────────────────────────────────────────────────

    public function saveAccount(): void
    {
        $user = $this->user();

        $data = $this->validate(
            [
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            ],
            attributes: ['name' => __('auth.name'), 'email' => __('auth.email')],
        );

        $user->update($data);

        $this->toast(__('profile.account.saved'));
    }

    public function updatedLocale(string $locale): void
    {
        if (! in_array($locale, config('app.available_locales'), true)) {
            $this->locale = app()->getLocale();

            return;
        }

        session()->put('locale', $locale);
        $this->user()->forceFill(['locale' => $locale])->save();
        app()->setLocale($locale);

        // Reload so <html lang dir> and the navigation follow the new language.
        Toast::success(__('profile.preferences.language_saved'));
        $this->redirect(route('profile'));
    }

    public function updatePassword(): void
    {
        $data = $this->validate(
            [
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', 'confirmed', Password::defaults()],
            ],
            attributes: [
                'current_password' => __('profile.password.current'),
                'password' => __('profile.password.new'),
            ],
        );

        $this->user()->update(['password' => $data['password']]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->toast(__('profile.password.saved'));
    }

    // ── Body data + goal ───────────────────────────────────────────────────────────

    public function editBody(): void
    {
        $this->resetErrorBag();
        $this->fillBodyFields($this->profile());
        $this->customising = false;
        $this->editingBody = true;
    }

    public function cancelBody(): void
    {
        $this->resetErrorBag();
        $this->fillBodyFields($this->profile());
        $this->editingBody = false;
    }

    public function saveBody(ProfileService $profiles, WeightService $weights): void
    {
        $this->resetErrorBag();

        $user = $this->user();
        $profile = $this->profile();
        $previousWeight = $profile->weight_kg;

        // Customised targets stay as the user set them; the service re-checks them against the new body data.
        $custom = $profile->targets_customized ? $this->storedTargets($profile) : null;

        try {
            $profile = $profiles->save(
                $user,
                [...$this->bodyData(), 'needs_medical_supervision' => $profile->needs_medical_supervision],
                $custom,
            );
        } catch (ValidationException $e) {
            $targetErrors = array_intersect_key($e->errors(), array_flip(self::customTargetFieldNames()));

            if ($targetErrors !== []) {
                // The stored custom targets no longer fit the new body data (e.g. a higher safe minimum).
                throw ValidationException::withMessages([
                    'custom_conflict' => collect($targetErrors)->flatten()->first(),
                ]);
            }

            throw $e;
        }

        if (abs($profile->weight_kg - $previousWeight) > 1e-9) {
            // Keep the weight history in step with the profile.
            $weights->record($user, Carbon::today(), $profile->weight_kg);
        }

        $this->fillBodyFields($profile);
        $this->editingBody = false;
        $this->toast(__($profile->targets_customized ? 'profile.body.saved_custom' : 'profile.body.saved'));
    }

    // ── Targets ────────────────────────────────────────────────────────────────────

    public function customise(): void
    {
        $this->resetErrorBag();
        $this->fillBodyFields($this->profile());
        $this->fillCustomTargets($this->profile()->targets());
        $this->editingBody = false;
        $this->customising = true;
    }

    public function cancelCustomise(): void
    {
        $this->resetErrorBag();
        $this->customising = false;
    }

    public function saveCustomTargets(ProfileService $profiles): void
    {
        $this->resetErrorBag();

        $profile = $this->profile();

        // The service validates against the safety floor and the macro/calorie balance.
        $profiles->save($this->user(), $this->bodyDataFrom($profile), $this->customTargets());

        $this->customising = false;
        $this->toast(__('profile.custom.saved'));
    }

    public function resetTargets(ProfileService $profiles): void
    {
        $this->resetErrorBag();

        $profiles->save($this->user(), $this->bodyDataFrom($this->profile()));

        $this->customising = false;
        $this->toast(__('profile.targets.reset_done'));
    }

    // ── Danger zone ────────────────────────────────────────────────────────────────

    public function deleteAccount(): void
    {
        $this->validate(
            ['delete_password' => ['required', 'string', 'current_password']],
            attributes: ['delete_password' => __('auth.password_label')],
        );

        $user = $this->user();
        $locale = app()->getLocale();

        Auth::guard('web')->logout();

        // Profile, weigh-ins, diary, custom foods, recipes and plans cascade in the schema.
        $user->delete();

        session()->invalidate();
        session()->regenerateToken();
        session()->put('locale', $locale);

        Toast::info(__('profile.delete.done'));

        $this->redirect(route('login'));
    }

    public function render(ProfileService $profiles)
    {
        $profile = $this->profile();
        $result = $profiles->resultFor($profile);

        return view('livewire.profile.settings', [
            'user' => $this->user(),
            'profile' => $profile,
            'targets' => $profile->targets(),
            'result' => $result,
            'minCalories' => (int) ceil($result->calorieFloor),
            'preview' => $this->editingBody ? $this->preview($profiles) : null,
            'macroCalories' => $this->customising ? $this->customMacroCalories() : null,
            'sexOptions' => $this->options(Sex::cases()),
            'activityOptions' => $this->options(ActivityLevel::cases()),
            'goalOptions' => $this->options(WeightGoal::cases()),
            'approachOptions' => collect(DietApproach::cases())->mapWithKeys(fn (DietApproach $approach) => [
                $approach->value => $approach->label().' · '.__('onboarding.goal.net_carbs_per_day', ['grams' => Wizard::netCarbRange($approach)]),
            ])->all(),
            'localeOptions' => collect(config('app.available_locales'))->mapWithKeys(fn (string $locale) => [$locale => __('app.locales.'.$locale)])->all(),
            'maxBirthDate' => Carbon::today()->subYears(ProfileService::MIN_AGE)->toDateString(),
            'minBirthDate' => Carbon::today()->subYears(ProfileService::MAX_AGE + 1)->addDay()->toDateString(),
        ])->title(__('profile.title'));
    }

    /** Targets the typed body data would give — shown before saving. Null while the form is invalid. */
    private function preview(ProfileService $profiles): ?MacroResult
    {
        try {
            return $profiles->preview($this->bodyData());
        } catch (ValidationException) {
            return null;
        }
    }

    /** @return array<string, float> the stored targets in ProfileService's $customTargets shape */
    private function storedTargets(Profile $profile): array
    {
        return [
            'calories' => $profile->target_calories,
            'protein_g' => $profile->target_protein_g,
            'fat_g' => $profile->target_fat_g,
            'net_carbs_g' => $profile->target_net_carbs_g,
            'carbs_g' => $profile->target_carbs_g,
        ];
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<string, string>
     */
    private function options(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }

    private function user(): User
    {
        return Auth::user();
    }

    private function profile(): Profile
    {
        return $this->user()->profile()->firstOrFail();
    }
}
