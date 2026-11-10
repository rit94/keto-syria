@php
    use App\View\Format;

    $kg = __('common.units.kg');
    $kcal = __('common.units.kcal');
@endphp

<div class="space-y-6">
    {{-- Medical supervision notice: stays for as long as the flag is set --}}
    @if ($profile->needs_medical_supervision)
        <x-alert variant="warning" :title="__('profile.medical.title')">
            {{ __('macros.conditions.advice') }}
        </x-alert>
    @endif

    {{-- Daily targets --}}
    <x-card :title="__('profile.targets.title')">
        <x-slot:actions>
            @if ($profile->targets_customized)
                <x-badge variant="info" icon="edit">{{ __('profile.targets.customised') }}</x-badge>
            @else
                <x-badge variant="success" icon="check">{{ __('profile.targets.automatic') }}</x-badge>
            @endif
        </x-slot:actions>

        @if (! $customising)
            @include('livewire.profile.partials.targets', ['targets' => $targets])

            <p class="mt-3 text-sm leading-relaxed text-ink-muted">
                {{ $profile->targets_customized ? __('profile.targets.customised_text') : __('profile.targets.automatic_text') }}
            </p>

            <details class="group mt-3 rounded-control bg-surface-2">
                <summary class="flex min-h-touch cursor-pointer list-none items-center justify-between gap-3 px-3 text-sm font-medium text-ink [&::-webkit-details-marker]:hidden">
                    <span>{{ __('onboarding.result.how') }}</span>
                    <x-icon name="chevron-down" size="sm" class="text-ink-muted group-open:rotate-180" />
                </summary>
                <dl class="space-y-2 border-t border-line px-3 py-3 text-sm">
                    @foreach ([
                        [__('onboarding.result.bmr'), $result->bmr],
                        [__('onboarding.result.tdee'), $result->tdee],
                        [__('onboarding.result.floor'), $minCalories],
                        [__('profile.targets.calculated_calories'), $result->roundedTargets()->calories],
                    ] as [$term, $value])
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-muted">{{ $term }}</dt>
                            <dd class="shrink-0 font-medium text-ink"><span class="num">@num($value)</span> {{ $kcal }}</dd>
                        </div>
                    @endforeach
                    @foreach ($result->warnings as $warning)
                        <p class="flex items-start gap-2 pt-1 leading-relaxed text-ink-muted">
                            <x-icon name="alert" size="sm" class="mt-0.5 text-warning-ink" />
                            <span>{{ __($warning) }}</span>
                        </p>
                    @endforeach
                </dl>
            </details>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <x-button variant="secondary" icon="edit" wire:click="customise">
                    {{ $profile->targets_customized ? __('profile.custom.edit') : __('profile.custom.start') }}
                </x-button>
                @if ($profile->targets_customized)
                    <x-button variant="ghost" wire:click="resetTargets">{{ __('profile.targets.reset') }}</x-button>
                @endif
            </div>
        @else
            <form wire:submit="saveCustomTargets" novalidate class="space-y-4">
                <p class="text-sm leading-relaxed text-ink-muted">{{ __('onboarding.result.customise_text') }}</p>

                @include('livewire.profile.partials.custom-targets', ['minCalories' => $minCalories, 'macroCalories' => $macroCalories])

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" wire:click="cancelCustomise">{{ __('common.cancel') }}</x-button>
                    <x-button type="submit" loading="saveCustomTargets">{{ __('profile.custom.save') }}</x-button>
                </div>
            </form>
        @endif

        <x-slot:footer>
            <p class="text-xs leading-relaxed text-ink-subtle">{{ __('macros.disclaimer_short') }}</p>
        </x-slot:footer>
    </x-card>

    {{-- Body data + goal --}}
    <x-card :title="__('profile.body.title')">
        @if (! $editingBody)
            <x-slot:actions>
                <x-button variant="ghost" size="sm" icon="edit" wire:click="editBody">{{ __('common.edit') }}</x-button>
            </x-slot:actions>

            <dl class="divide-y divide-line text-[0.9375rem]">
                @foreach ([
                    [__('macros.fields.sex'), $profile->sex->label(), null, false],
                    [__('profile.body.age'), Format::number($profile->age()), __('profile.body.years'), true],
                    [__('macros.fields.height_cm'), Format::number($profile->height_cm, 1), __('onboarding.units.cm'), true],
                    [__('onboarding.body.weight'), Format::number($profile->weight_kg, 1), $kg, true],
                    [__('macros.fields.goal_weight_kg'), Format::number($profile->goal_weight_kg, 1), $profile->goal_weight_kg === null ? null : $kg, true],
                    [__('macros.fields.activity_level'), $profile->activity_level->label(), null, false],
                    [__('macros.fields.goal'), $profile->goal->label(), null, false],
                    [__('macros.fields.approach'), $profile->approach->label(), null, false],
                ] as [$term, $value, $unit, $numeric])
                    <div class="flex items-baseline justify-between gap-4 py-2.5 first:pt-0 last:pb-0">
                        <dt class="text-ink-muted">{{ $term }}</dt>
                        <dd class="text-end font-medium text-ink">
                            @if ($numeric)
                                <span class="num">{{ $value }}</span> {{ $unit }}
                            @else
                                {{ $value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @else
            <form wire:submit="saveBody" novalidate class="space-y-4">
                <x-field :label="__('macros.fields.sex')" name="sex">
                    <x-segmented wire:model.live="sex" :label="__('macros.fields.sex')" :options="$sexOptions" />
                </x-field>

                <x-input wire:model.live.debounce.500ms="birth_date" type="date" :label="__('macros.fields.birth_date')"
                         min="{{ $minBirthDate }}" max="{{ $maxBirthDate }}" />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-input wire:model.live.debounce.500ms="height_cm" type="number" inputmode="decimal" step="0.5"
                             :label="__('macros.fields.height_cm')" :suffix="__('onboarding.units.cm')" />
                    <x-input wire:model.live.debounce.500ms="weight_kg" type="number" inputmode="decimal" step="0.1"
                             :label="__('onboarding.body.weight')" :suffix="$kg" />
                    <x-input wire:model.live.debounce.500ms="goal_weight_kg" type="number" inputmode="decimal" step="0.1" optional
                             :label="__('macros.fields.goal_weight_kg')" :suffix="$kg" />
                </div>

                <x-select wire:model.live="activity_level" :label="__('macros.fields.activity_level')" :options="$activityOptions"
                          :hint="$activity_level !== '' ? __('onboarding.activity.levels.'.$activity_level) : null" />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-select wire:model.live="goal" :label="__('macros.fields.goal')" :options="$goalOptions" />
                    <x-select wire:model.live="approach" :label="__('macros.fields.approach')" :options="$approachOptions" />
                </div>

                {{-- Live preview of what saving would do --}}
                <div class="rounded-card border border-line bg-surface-2 p-3.5" aria-live="polite">
                    <p class="text-sm font-semibold text-ink">{{ __('profile.body.preview_title') }}</p>

                    @if ($preview === null)
                        <p class="mt-1 text-sm text-ink-muted">{{ __('profile.body.preview_invalid') }}</p>
                    @elseif ($profile->targets_customized)
                        <p class="mt-1 text-sm leading-relaxed text-ink-muted">{{ __('profile.body.preview_custom') }}</p>
                        <p class="mt-2 text-sm text-ink">
                            {{ __('onboarding.result.floor') }}:
                            <span class="num font-semibold">@num(ceil($preview->calorieFloor))</span> {{ $kcal }}
                        </p>
                    @else
                        @php $next = $preview->roundedTargets(); @endphp
                        <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-5">
                            @foreach ([
                                [__('common.macros.calories'), $next->calories, $targets->calories, $kcal],
                                [__('common.macros.net_carbs'), $next->netCarbs, $targets->netCarbs, __('common.units.g')],
                                [__('common.macros.protein'), $next->protein, $targets->protein, __('common.units.g')],
                                [__('common.macros.fat'), $next->fat, $targets->fat, __('common.units.g')],
                                [__('profile.targets.total_carbs'), $next->carbs, $targets->carbs, __('common.units.g')],
                            ] as [$term, $new, $old, $unit])
                                <div>
                                    <dt class="text-ink-muted">{{ $term }}</dt>
                                    <dd class="font-semibold text-ink">
                                        <span class="num">@num($new)</span> <span class="font-normal text-ink-muted">{{ $unit }}</span>
                                        @if (Format::number($new) !== Format::number($old))
                                            <span class="block text-xs font-normal text-ink-subtle">{{ __('profile.body.preview_was') }} <span class="num">@num($old)</span></span>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>

                        @foreach ($preview->warnings as $warning)
                            <p class="mt-2.5 flex items-start gap-2 text-sm leading-relaxed text-ink-muted" wire:key="preview-{{ $warning }}">
                                <x-icon name="alert" size="sm" class="mt-0.5 text-warning-ink" />
                                <span>{{ __($warning) }}</span>
                            </p>
                        @endforeach
                    @endif
                </div>

                @error('custom_conflict')
                    <x-alert variant="danger" :title="__('profile.body.custom_conflict_title')">
                        <p>{{ $message }}</p>
                        <p class="mt-1 text-sm text-ink-muted">{{ __('profile.body.custom_conflict_text') }}</p>
                    </x-alert>
                @enderror

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" wire:click="cancelBody">{{ __('common.cancel') }}</x-button>
                    <x-button type="submit" loading="saveBody">{{ __('common.save') }}</x-button>
                </div>
            </form>
        @endif
    </x-card>

    {{-- Account --}}
    <x-card :title="__('profile.account.title')">
        <form wire:submit="saveAccount" novalidate class="space-y-4">
            <x-input wire:model="name" :label="__('auth.name')" autocomplete="name" />
            <x-input wire:model="email" type="email" :label="__('auth.email')" autocomplete="email" inputmode="email" />
            <div class="flex justify-end">
                <x-button type="submit" loading="saveAccount" class="w-full sm:w-auto">{{ __('common.save') }}</x-button>
            </div>
        </form>
    </x-card>

    {{-- Language + theme --}}
    <x-card :title="__('profile.preferences.title')">
        <div class="space-y-4">
            <x-segmented wire:model.live="locale" :label="__('app.language')" showLabel :options="$localeOptions" />

            <div>
                <p class="mb-1.5 text-sm font-medium text-ink">{{ __('app.theme.label') }}</p>
                <x-theme-toggle labelled class="border border-line-strong" />
            </div>
        </div>
    </x-card>

    {{-- Password --}}
    <x-card :title="__('profile.password.title')">
        <form wire:submit="updatePassword" novalidate class="space-y-4">
            <x-input wire:model="current_password" type="password" :label="__('profile.password.current')" autocomplete="current-password" />
            <x-input wire:model="password" type="password" :label="__('profile.password.new')" :hint="__('auth.password_hint')" autocomplete="new-password" />
            <x-input wire:model="password_confirmation" type="password" :label="__('auth.password_confirmation')" autocomplete="new-password" />
            <div class="flex justify-end">
                <x-button type="submit" loading="updatePassword" class="w-full sm:w-auto">{{ __('profile.password.submit') }}</x-button>
            </div>
        </form>
    </x-card>

    {{-- Session + danger zone --}}
    <x-card>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button type="submit" variant="secondary" icon="logout" block :loading="false">{{ __('auth.logout') }}</x-button>
        </form>

        <div class="mt-5 border-t border-line pt-4">
            <h2 class="font-semibold text-ink">{{ __('profile.delete.title') }}</h2>
            <p class="mt-1 text-sm leading-relaxed text-ink-muted">{{ __('profile.delete.text') }}</p>
            <x-button variant="danger-ghost" icon="trash" class="mt-2 -ms-3" x-on:click="$dispatch('open-modal', 'delete-account')">
                {{ __('profile.delete.open') }}
            </x-button>
        </div>
    </x-card>

    <x-modal name="delete-account" :title="__('profile.delete.confirm_title')" size="sm">
        <form wire:submit="deleteAccount" novalidate id="delete-account-form" class="space-y-4">
            <p class="text-[0.9375rem] leading-relaxed text-ink-muted">{{ __('profile.delete.confirm_text') }}</p>
            <x-input wire:model="delete_password" type="password" :label="__('auth.password_label')" autocomplete="current-password" />
        </form>

        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'delete-account')">{{ __('common.cancel') }}</x-button>
            <x-button variant="danger" type="submit" form="delete-account-form" loading="deleteAccount">{{ __('profile.delete.submit') }}</x-button>
        </x-slot:footer>
    </x-modal>
</div>
