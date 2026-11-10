@php
    use App\Livewire\Onboarding\Wizard;
    use App\View\Format;
@endphp

<div>
    {{-- Progress --}}
    <div class="mb-6">
        <div class="flex items-center justify-between gap-3 text-sm text-ink-muted">
            <span>{{ __('onboarding.step_of', ['step' => $step, 'total' => $totalSteps]) }}</span>
            @if ($step > Wizard::STEP_WELCOME)
                <button type="button" wire:click="back" class="-me-2 flex min-h-touch items-center gap-1 rounded-control px-2 font-medium text-ink-muted transition-colors duration-150 hover:text-ink">
                    <x-icon name="chevron-start" size="sm" />
                    <span>{{ __('common.back') }}</span>
                </button>
            @else
                <span class="min-h-touch"></span>
            @endif
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-track" role="progressbar" aria-valuemin="1" aria-valuemax="{{ $totalSteps }}" aria-valuenow="{{ $step }}" aria-label="{{ __('onboarding.progress') }}">
            <div class="h-full rounded-full bg-accent" style="width: {{ round($step / $totalSteps * 100) }}%"></div>
        </div>
    </div>

    <form wire:submit="next" novalidate wire:key="step-{{ $step }}">
        @switch($step)
            {{-- 1 · Welcome + language --}}
            @case(Wizard::STEP_WELCOME)
                <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('onboarding.welcome.heading', ['name' => auth()->user()->name]) }}</h1>
                <p class="mt-2 leading-relaxed text-ink-muted">{{ __('onboarding.welcome.text') }}</p>

                <fieldset class="mt-6">
                    <legend class="mb-2 text-sm font-medium text-ink">{{ __('onboarding.welcome.language') }}</legend>
                    <div class="grid grid-cols-2 gap-2.5">
                        @foreach (config('app.available_locales') as $locale)
                            @php $current = app()->getLocale() === $locale; @endphp
                            <button
                                type="button"
                                wire:click="chooseLocale('{{ $locale }}')"
                                lang="{{ $locale }}"
                                aria-pressed="{{ $current ? 'true' : 'false' }}"
                                @class([
                                    'flex min-h-touch items-center justify-center gap-2 rounded-card border px-3 py-3 font-semibold transition-colors duration-150',
                                    'border-accent bg-accent-soft text-accent-ink' => $current,
                                    'border-line-strong bg-surface text-ink hover:bg-surface-2' => ! $current,
                                ])
                            >
                                @if ($current)<x-icon name="check" size="sm" />@endif
                                <span>{{ __('app.locales.'.$locale) }}</span>
                            </button>
                        @endforeach
                    </div>
                </fieldset>

                <ul class="mt-6 space-y-3 text-[0.9375rem] leading-relaxed text-ink-muted">
                    @foreach (['time', 'private', 'change'] as $point)
                        <li class="flex items-start gap-2.5">
                            <x-icon name="check" size="sm" class="mt-1 text-accent-ink" />
                            <span>{{ __('onboarding.welcome.points.'.$point) }}</span>
                        </li>
                    @endforeach
                </ul>
                @break

            {{-- 2 · Sex + birth date --}}
            @case(Wizard::STEP_ABOUT)
                <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('onboarding.about.heading') }}</h1>
                <p class="mt-2 leading-relaxed text-ink-muted">{{ __('onboarding.about.text') }}</p>

                <div class="mt-6 space-y-5">
                    <x-field :label="__('onboarding.about.sex')" name="sex" :hint="__('onboarding.about.sex_hint')">
                        <x-segmented wire:model="sex" :label="__('onboarding.about.sex')" :options="$sexOptions" />
                    </x-field>

                    <x-input wire:model="birth_date" type="date" :label="__('onboarding.about.birth_date')"
                             min="{{ $minBirthDate }}" max="{{ $maxBirthDate }}" autocomplete="bday" />
                </div>
                @break

            {{-- 3 · Height, weight, goal weight --}}
            @case(Wizard::STEP_BODY)
                <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('onboarding.body.heading') }}</h1>
                <p class="mt-2 leading-relaxed text-ink-muted">{{ __('onboarding.body.text') }}</p>

                <div class="mt-6 space-y-4">
                    <x-input wire:model="height_cm" type="number" inputmode="decimal" step="0.5"
                             :label="__('onboarding.body.height')" :suffix="__('onboarding.units.cm')" />
                    <x-input wire:model="weight_kg" type="number" inputmode="decimal" step="0.1"
                             :label="__('onboarding.body.weight')" :suffix="__('common.units.kg')" />
                    <x-input wire:model="goal_weight_kg" type="number" inputmode="decimal" step="0.1" optional
                             :label="__('onboarding.body.goal_weight')" :suffix="__('common.units.kg')"
                             :hint="__('onboarding.body.goal_weight_hint')" />
                </div>
                @break

            {{-- 4 · Activity --}}
            @case(Wizard::STEP_ACTIVITY)
                <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('onboarding.activity.heading') }}</h1>
                <p class="mt-2 leading-relaxed text-ink-muted">{{ __('onboarding.activity.text') }}</p>

                <fieldset class="mt-6">
                    <legend class="sr-only">{{ __('onboarding.activity.heading') }}</legend>
                    <div class="space-y-2.5">
                        @foreach ($activityOptions as $option)
                            @include('livewire.onboarding.partials.choice', ['model' => 'activity_level', ...$option])
                        @endforeach
                    </div>
                    <x-field name="activity_level" class="mt-2" />
                </fieldset>
                @break

            {{-- 5 · Goal + approach --}}
            @case(Wizard::STEP_GOAL)
                <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('onboarding.goal.heading') }}</h1>

                <fieldset class="mt-5">
                    <legend class="mb-2 text-sm font-medium text-ink">{{ __('onboarding.goal.goal_label') }}</legend>
                    <div class="space-y-2.5">
                        @foreach ($goalOptions as $option)
                            @include('livewire.onboarding.partials.choice', ['model' => 'goal', ...$option])
                        @endforeach
                    </div>
                    <x-field name="goal" class="mt-2" />
                </fieldset>

                <fieldset class="mt-6">
                    <legend class="mb-1 text-sm font-medium text-ink">{{ __('onboarding.goal.approach_label') }}</legend>
                    <p class="mb-2 text-sm leading-relaxed text-ink-muted">{{ __('onboarding.goal.approach_text') }}</p>
                    <div class="space-y-2.5">
                        @foreach ($approachOptions as $option)
                            @include('livewire.onboarding.partials.choice', ['model' => 'approach', ...$option])
                        @endforeach
                    </div>
                    <x-field name="approach" class="mt-2" />
                </fieldset>
                @break

            {{-- 6 · Health check --}}
            @case(Wizard::STEP_HEALTH)
                <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('macros.conditions.title') }}</h1>
                <p class="mt-2 leading-relaxed text-ink-muted">{{ __('macros.conditions.intro') }}</p>

                <fieldset class="mt-5">
                    <legend class="sr-only">{{ __('macros.conditions.title') }}</legend>

                    <div class="divide-y divide-line rounded-card border border-line bg-surface px-3.5">
                        @foreach ($conditionOptions as $key => $label)
                            <x-checkbox wire:model.live="conditions" value="{{ $key }}" id="condition-{{ $key }}" class="self-start mt-3">
                                <span class="block py-2.5 leading-relaxed">{{ $label }}</span>
                            </x-checkbox>
                        @endforeach
                    </div>

                    <div class="mt-2.5 rounded-card border border-line bg-surface px-3.5">
                        <x-checkbox wire:model.live="none_apply" :label="__('macros.conditions.none')" />
                    </div>

                    <x-field name="conditions" class="mt-2" />
                </fieldset>

                @if ($conditions !== [])
                    <x-alert variant="warning" :title="__('onboarding.health.advice_title')" class="mt-4">
                        <p>{{ __('macros.conditions.advice') }}</p>
                        <p class="mt-1.5 text-sm text-ink-muted">{{ __('onboarding.health.continue_note') }}</p>
                    </x-alert>
                @endif

                <p class="mt-4 text-sm leading-relaxed text-ink-subtle">{{ __('onboarding.health.privacy') }}</p>
                @break

            {{-- 7 · Result --}}
            @case(Wizard::STEP_RESULT)
                @if ($result)
                    <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('onboarding.result.heading') }}</h1>
                    <p class="mt-2 leading-relaxed text-ink-muted">{{ __('onboarding.result.text') }}</p>

                    <x-card class="mt-5">
                        @include('livewire.profile.partials.targets', ['targets' => $targets])
                    </x-card>

                    @if ($conditions !== [] || $result->requiresMedicalAdvice)
                        <x-alert variant="warning" :title="__('onboarding.health.advice_title')" class="mt-4">
                            {{ __('macros.conditions.advice') }}
                        </x-alert>
                    @endif

                    @foreach ($result->warnings as $warning)
                        <x-alert variant="warning" class="mt-3" wire:key="warning-{{ $warning }}">{{ __($warning) }}</x-alert>
                    @endforeach

                    {{-- How this was calculated --}}
                    <details class="group mt-4 rounded-card border border-line bg-surface">
                        <summary class="flex min-h-touch cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-medium text-ink [&::-webkit-details-marker]:hidden">
                            <span>{{ __('onboarding.result.how') }}</span>
                            <x-icon name="chevron-down" size="sm" class="text-ink-muted group-open:rotate-180" />
                        </summary>

                        <dl class="space-y-4 border-t border-line px-4 py-4 text-sm leading-relaxed">
                            @php
                                $kcal = __('common.units.kcal');
                                $adjustment = $result->calorieAdjustment;
                                $rows = [
                                    [__('onboarding.result.bmr'), Format::number($result->bmr).' '.$kcal, __('macros.explain.bmr')],
                                    [__('onboarding.result.tdee'), Format::number($result->tdee).' '.$kcal, __('macros.explain.tdee')],
                                    [__('onboarding.result.adjustment'), ($adjustment > 0 ? '+' : ($adjustment < 0 ? '−' : '')).Format::number(abs($adjustment)).' '.$kcal, __('macros.explain.adjustment_'.$goal)],
                                    [__('onboarding.result.floor'), Format::number($result->calorieFloor).' '.$kcal, __('macros.explain.floor')],
                                    [__('common.macros.protein'), Format::number($result->targets->protein).' '.__('common.units.g'), __('macros.explain.protein', ['per_kg' => Format::number($result->proteinPerKg, 1), 'reference_kg' => Format::number($result->referenceWeightKg, 1)])],
                                    [__('common.macros.net_carbs'), Format::number($result->targets->netCarbs).' '.__('common.units.g'), __('macros.explain.net_carbs')],
                                    [__('profile.targets.total_carbs'), Format::number($result->targets->carbs).' '.__('common.units.g'), __('macros.explain.total_carbs')],
                                    [__('common.macros.fat'), Format::number($result->targets->fat).' '.__('common.units.g'), __('macros.explain.fat')],
                                ];
                            @endphp

                            @foreach ($rows as [$term, $value, $explanation])
                                <div>
                                    <dt class="flex items-baseline justify-between gap-3 font-semibold text-ink">
                                        <span>{{ $term }}</span>
                                        <span class="num shrink-0">{{ $value }}</span>
                                    </dt>
                                    <dd class="mt-0.5 text-ink-muted">{{ $explanation }}</dd>
                                </div>
                            @endforeach

                            <p class="text-ink-subtle">{{ __('macros.explain.rounding') }}</p>
                        </dl>
                    </details>

                    {{-- Customise --}}
                    <div class="mt-3 rounded-card border border-line bg-surface px-4 py-1.5">
                        <x-checkbox wire:model.live="customise" :label="__('onboarding.result.customise')" />

                        @if ($customise)
                            <div class="border-t border-line pb-3 pt-4">
                                <p class="mb-4 text-sm leading-relaxed text-ink-muted">{{ __('onboarding.result.customise_text') }}</p>
                                @include('livewire.profile.partials.custom-targets', [
                                    'minCalories' => (int) ceil($result->calorieFloor),
                                    'macroCalories' => $macroCalories,
                                ])
                            </div>
                        @endif
                    </div>

                    <p class="mt-4 text-sm leading-relaxed text-ink-subtle">{{ __('macros.disclaimer') }}</p>
                @endif
                @break
        @endswitch

        <div class="mt-8">
            <x-button type="submit" loading="next" size="lg" block :iconEnd="$step < $totalSteps ? 'arrow-end' : null">
                {{ match (true) {
                    $step === Wizard::STEP_WELCOME => __('onboarding.start'),
                    $step === $totalSteps => __('onboarding.finish'),
                    default => __('common.next'),
                } }}
            </x-button>
        </div>
    </form>
</div>
