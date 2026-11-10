@php
    use App\Livewire\Foods\Support\UnitLabels;
    use App\View\Format;

    $g = __('common.units.g');
    $kcal = __('common.units.kcal');
    $totals = $summary->totals;
    $targets = $summary->targets;
    $remaining = $summary->remaining;
    $incomplete = ! $totals->isComplete();
    $netOver = $remaining !== null && round($remaining->netCarbs, 1) < 0;
@endphp

<x-slot:context>@date($day, 'weekday')</x-slot:context>

<div class="space-y-4">
    {{-- Date navigation --}}
    <nav class="flex items-center gap-1" aria-label="{{ __('today.date.nav') }}">
        <x-button variant="ghost" square icon="chevron-start" wire:click="previousDay" aria-label="{{ __('today.date.previous') }}" />

        <div class="relative flex min-w-0 flex-1 justify-center" x-data>
            <button
                type="button"
                x-on:click="$refs.date.showPicker ? $refs.date.showPicker() : $refs.date.focus()"
                class="inline-flex min-h-touch min-w-0 items-center gap-2 rounded-control px-3 text-ink transition-colors duration-150 hover:bg-surface-2"
                aria-label="{{ __('today.date.pick') }}"
            >
                <x-icon name="calendar" size="sm" class="shrink-0 text-ink-muted" />
                <span class="truncate text-base font-semibold">{{ Format::relativeDay($day) }}</span>
                <span class="shrink-0 text-sm text-ink-muted">@date($day, 'short')</span>
            </button>
            <input
                x-ref="date"
                type="date"
                tabindex="-1"
                aria-hidden="true"
                class="pointer-events-none absolute bottom-0 start-1/2 size-px opacity-0"
                value="{{ $day->toDateString() }}"
                max="{{ $maxDate }}"
                x-on:change="$event.target.value && $wire.goTo($event.target.value)"
            >
        </div>

        <x-button variant="secondary" size="sm" wire:click="goToday" :class="$isToday ? 'invisible' : ''" :aria-hidden="$isToday ? 'true' : 'false'" :tabindex="$isToday ? -1 : 0">
            {{ __('common.today') }}
        </x-button>
        <x-button variant="ghost" square icon="chevron-end" wire:click="nextDay" :disabled="$isToday" aria-label="{{ __('today.date.next') }}" />
    </nav>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start lg:gap-6">
        <div class="min-w-0 space-y-4">
            {{-- Remaining macros --}}
            <x-card as="section" aria-label="{{ __('today.hero.label') }}">
                <div class="flex items-center gap-4">
                    <div class="min-w-0 flex-1">
                        @if ($remaining)
                            <p class="text-sm font-medium text-ink-muted">
                                {{ $netOver ? __('today.hero.net_over') : __('today.hero.net_left') }}
                            </p>
                            <p class="mt-1 leading-none {{ $netOver ? 'text-danger-ink' : 'text-ink' }}">
                                <span class="num text-5xl font-semibold tracking-tight">@num(abs($remaining->netCarbs), 1)</span>
                                <span class="text-lg font-medium text-ink-muted">{{ $g }}</span>
                            </p>
                            <p class="mt-2 text-sm text-ink-muted">
                                {{ __('today.hero.eaten_of') }}
                                <span class="num font-medium text-ink">@num($totals->netCarbs(), 1) / @num($targets->netCarbs, 1)</span> {{ $g }}
                            </p>
                        @else
                            <p class="text-sm font-medium text-ink-muted">{{ __('today.hero.net_eaten') }}</p>
                            <p class="mt-1 leading-none text-ink">
                                <span class="num text-5xl font-semibold tracking-tight">@num($totals->netCarbs(), 1)</span>
                                <span class="text-lg font-medium text-ink-muted">{{ $g }}</span>
                            </p>
                            <p class="mt-2 text-sm text-ink-muted">{{ __('today.hero.no_targets') }}</p>
                        @endif
                    </div>

                    <x-macro-ring
                        class="shrink-0"
                        :label="__('common.macros.net_carbs')"
                        macro="carbs"
                        :value="$totals->netCarbs()"
                        :target="$targets?->netCarbs"
                        :left="$remaining?->netCarbs"
                        :unit="$g"
                        :decimals="1"
                    />
                </div>

                <div class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-3">
                    <x-macro-bar :label="__('common.macros.calories')" macro="kcal" :value="$totals->calories" :target="$targets?->calories" :left="$remaining?->calories" :unit="$kcal" />
                    <x-macro-bar :label="__('common.macros.protein')" macro="protein" :limit="false" :value="$totals->protein" :target="$targets?->protein" :left="$remaining?->protein" :unit="$g" />
                    <x-macro-bar :label="__('common.macros.fat')" macro="fat" :value="$totals->fat" :target="$targets?->fat" :left="$remaining?->fat" :unit="$g" />
                </div>

                <dl class="mt-4 flex flex-wrap gap-x-5 gap-y-1 border-t border-line pt-3 text-sm text-ink-muted">
                    <div class="flex gap-1.5">
                        <dt>{{ __('today.hero.total_carbs') }}</dt>
                        <dd><span class="num font-medium text-ink">@num($totals->carbs, 1)</span> {{ $g }}</dd>
                    </div>
                    <div class="flex gap-1.5">
                        <dt>{{ __('common.macros.fiber') }}</dt>
                        <dd>
                            @if ($totals->isMissing('fiber'))<span class="sr-only">{{ __('common.at_least') }}</span>@endif
                            <span class="num font-medium text-ink">{{ $totals->isMissing('fiber') ? '≥ ' : '' }}@num($totals->fiber, 1)</span> {{ $g }}
                        </dd>
                    </div>
                    <div class="flex gap-1.5">
                        <dt>{{ __('today.hero.saturated_fat') }}</dt>
                        <dd>
                            @if ($totals->isMissing('saturated_fat'))<span class="sr-only">{{ __('common.at_least') }}</span>@endif
                            <span class="num font-medium text-ink">{{ $totals->isMissing('saturated_fat') ? '≥ ' : '' }}@num($totals->saturatedFat, 1)</span> {{ $g }}
                        </dd>
                    </div>
                </dl>

                @if ($incomplete)
                    <x-alert variant="info" class="mt-3" :title="__('today.incomplete.title')">
                        <ul class="space-y-1">
                            @if ($missingFiber > 0)
                                <li>{{ trans_choice('today.incomplete.fiber', $missingFiber, ['count' => $missingFiber]) }}</li>
                            @endif
                            @if ($missingSaturated > 0)
                                <li>{{ trans_choice('today.incomplete.saturated_fat', $missingSaturated, ['count' => $missingSaturated]) }}</li>
                            @endif
                        </ul>
                    </x-alert>
                @endif
            </x-card>

            {{-- What have I eaten: the four meals, always in the same order --}}
            @foreach ($summary->byMeal as $mealValue => $meal)
                @php $mealEnum = \App\Enums\Meal::from($mealValue); @endphp
                <x-card as="section" padding="none" wire:key="meal-{{ $mealValue }}" aria-labelledby="meal-{{ $mealValue }}-title">
                    <div class="flex items-center gap-3 py-2 ps-4 pe-2">
                        <div class="min-w-0 flex-1">
                            <h2 id="meal-{{ $mealValue }}-title" class="text-base font-semibold leading-snug text-ink">{{ $mealEnum->label() }}</h2>
                            <p class="text-sm leading-snug text-ink-muted">
                                @if ($meal['entries']->isEmpty())
                                    {{ __('today.meal.empty') }}
                                @else
                                    <span class="num">@num($meal['totals']->calories)</span> {{ $kcal }}
                                    ·
                                    <span class="num">@num($meal['totals']->netCarbs(), 1)</span> {{ $g }} {{ __('foods.short.net_carbs') }}
                                @endif
                            </p>
                        </div>
                        <x-button variant="ghost" size="sm" icon="plus" wire:click="openAdd('{{ $mealValue }}')" aria-label="{{ __('today.meal.add_to', ['meal' => $mealEnum->label()]) }}">
                            {{ __('common.add') }}
                        </x-button>
                    </div>

                    @if ($meal['entries']->isNotEmpty())
                        <ul class="divide-y divide-line border-t border-line">
                            @foreach ($meal['entries'] as $entry)
                                <li wire:key="entry-{{ $entry->id }}">
                                    <x-list-row
                                        as="button"
                                        wire:click="edit({{ $entry->id }})"
                                        :title="$entry->description"
                                        :value="Format::number($entry->calories)"
                                        :valueHint="$kcal"
                                        aria-label="{{ __('today.entry.edit', ['name' => $entry->description]) }}"
                                    >
                                        <span class="text-sm text-ink-muted">
                                            <span class="num">@num($entry->quantity, 2)</span> {{ UnitLabels::forEntry($entry) }}
                                            ·
                                            <span class="num">@num($entry->nutrients()->netCarbs(), 1)</span> {{ $g }} {{ __('foods.short.net_carbs') }}
                                        </span>
                                        @if ($entry->weighed_cooked)
                                            <x-badge>{{ __('today.entry.cooked_weight') }}</x-badge>
                                        @endif
                                        @if ($entry->is_estimate)
                                            <x-badge estimate />
                                        @endif
                                    </x-list-row>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endforeach
        </div>

        <div class="min-w-0 space-y-4">
            {{-- What can I eat next --}}
            <x-card as="section" padding="none" aria-labelledby="next-title">
                <div class="px-4 pt-3.5 pb-2">
                    <h2 id="next-title" class="text-base font-semibold leading-snug text-ink">{{ __('today.next.title') }}</h2>
                    <p class="text-sm leading-snug text-ink-muted">
                        @if (! $remaining)
                            {{ __('today.next.no_targets') }}
                        @elseif ($remaining->calories <= 0)
                            {{ __('today.next.calories_reached') }}
                        @elseif ($suggestions->isEmpty())
                            {{ __('today.next.nothing_fits') }}
                        @else
                            {{ __('today.next.fits', ['carbs' => Format::number(max($remaining->netCarbs, 0), 1), 'kcal' => Format::number($remaining->calories)]) }}
                        @endif
                    </p>
                </div>

                @if ($suggestions->isNotEmpty())
                    <ul class="divide-y divide-line border-t border-line">
                        @foreach ($suggestions as $suggestion)
                            @php $shown = $suggestion['nutrients']->rounded(); @endphp
                            <li wire:key="suggestion-{{ $suggestion['type'] }}-{{ $suggestion['id'] }}">
                                <x-list-row
                                    as="button"
                                    wire:click="{{ $suggestion['type'] === 'recipe' ? 'suggestRecipe' : 'suggestFood' }}({{ $suggestion['id'] }})"
                                    :title="$suggestion['name']"
                                    :value="Format::number($shown['calories'])"
                                    :valueHint="$kcal"
                                >
                                    <span class="text-sm text-ink-muted">
                                        <span class="num">@num($suggestion['quantity'], 2)</span> {{ $suggestion['unit_label'] }}
                                        ·
                                        <span class="num">@num($shown['net_carbs_g'], 1)</span> {{ $g }} {{ __('foods.short.net_carbs') }}
                                        ·
                                        {{ __('foods.short.protein') }} <span class="num">@num($shown['protein_g'], 1)</span>
                                        ·
                                        {{ __('foods.short.fat') }} <span class="num">@num($shown['fat_g'], 1)</span>
                                    </span>
                                    @if ($suggestion['type'] === 'recipe')
                                        <x-badge variant="accent">{{ __('today.next.recipe') }}</x-badge>
                                    @endif
                                    @if ($suggestion['is_estimate'])
                                        <x-badge estimate />
                                    @endif
                                </x-list-row>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (Route::has('coach') || Route::has('log.smart'))
                    <div class="flex flex-wrap gap-2 border-t border-line p-3">
                        @if (Route::has('coach'))
                            <x-button variant="secondary" size="sm" icon="chat" :href="route('coach')" navigate>{{ __('today.next.ask_coach') }}</x-button>
                        @endif
                        @if (Route::has('log.smart'))
                            <x-button variant="secondary" size="sm" icon="sparkles" :href="route('log.smart')" navigate>{{ __('today.next.smart_log') }}</x-button>
                        @endif
                    </div>
                @endif
            </x-card>

            {{-- Quick add from the user's own history --}}
            <x-card as="section" padding="none" aria-labelledby="quick-title">
                <div class="px-4 pt-3.5">
                    <h2 id="quick-title" class="text-base font-semibold leading-snug text-ink">{{ __('today.quick.title') }}</h2>
                </div>

                @if ($recent->isEmpty())
                    <x-empty-state icon="clock" compact :title="__('today.quick.empty_title')" :text="__('today.quick.empty_text')">
                        <x-button icon="plus" wire:click="openAdd('{{ $addMeal }}')">{{ __('today.quick.first') }}</x-button>
                    </x-empty-state>
                @else
                    <x-tabs class="px-1" :tabs="['recent' => __('today.quick.recent'), 'frequent' => __('today.quick.frequent')]" active="recent" :label="__('today.quick.title')">
                        @foreach (['recent' => $recent, 'frequent' => $frequent] as $panel => $items)
                            <x-tabs.panel :name="$panel" class="-mt-4">
                                <ul class="divide-y divide-line">
                                    @foreach ($items as $item)
                                        <li wire:key="quick-{{ $panel }}-{{ $item->id }}" class="flex items-center gap-1 ps-3 pe-1">
                                            <button
                                                type="button"
                                                wire:click="again({{ $item->id }})"
                                                class="min-w-0 flex-1 rounded-control py-2.5 text-start transition-colors duration-150 hover:bg-surface-2"
                                            >
                                                <span class="block truncate text-[0.9375rem] font-medium leading-snug text-ink">{{ $item->food->displayName() }}</span>
                                                <span class="block truncate text-sm leading-snug text-ink-muted">
                                                    <span class="num">@num($item->quantity, 2)</span> {{ UnitLabels::forEntry($item) }}
                                                    ·
                                                    <span class="num">@num($item->calories)</span> {{ $kcal }}
                                                </span>
                                            </button>
                                            <x-button
                                                variant="ghost"
                                                square
                                                icon="plus"
                                                wire:click="repeat({{ $item->id }})"
                                                aria-label="{{ __('today.quick.repeat', ['name' => $item->food->displayName()]) }}"
                                                title="{{ __('today.quick.repeat_hint') }}"
                                            />
                                        </li>
                                    @endforeach
                                </ul>
                            </x-tabs.panel>
                        @endforeach
                    </x-tabs>
                @endif
            </x-card>
        </div>
    </div>

    {{-- Add: the shared food picker in a sheet --}}
    <x-bottom-sheet name="add-food" :title="__('today.add.title')" size="lg">
        <div class="space-y-4">
            <x-segmented wire:model.live="addMeal" :label="__('today.edit.meal')" :options="$mealOptions" />
            <livewire:foods.picker :context="\App\Livewire\Today\Index::PICKER_CONTEXT" :with-quantity="true" wire:key="today-picker" />
        </div>
    </x-bottom-sheet>

    {{-- Add a suggested recipe --}}
    <x-bottom-sheet name="add-recipe" :title="$recipe?->name ?? __('today.next.recipe')">
        <form wire:submit="logRecipe" id="add-recipe-form" class="space-y-4" novalidate>
            <x-segmented wire:model.live="addMeal" name="recipe-meal" :label="__('today.edit.meal')" :options="$mealOptions" />
            <x-number-stepper
                wire:model.live.debounce.300ms="recipeServings"
                :label="__('today.recipe.servings')"
                :step="0.5"
                :unit="__('units.serving')"
                :error="$errors->first('recipeServings') ?: $recipeError"
            />
            <div wire:loading.class="opacity-60" wire:target="recipeServings" class="transition-opacity duration-150">
                @include('livewire.foods.partials.nutrient-tiles', ['nutrients' => $recipePreview])
            </div>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'add-recipe')">{{ __('common.cancel') }}</x-button>
            <x-button type="submit" form="add-recipe-form" loading="logRecipe" icon="check">{{ __('common.add') }}</x-button>
        </x-slot:footer>
    </x-bottom-sheet>

    {{-- Edit an entry --}}
    <x-bottom-sheet name="edit-entry" :title="$editing?->description ?? __('today.edit.title')">
        <form wire:submit="saveEdit" id="edit-entry-form" class="space-y-4" novalidate>
            @if ($editing)
                <div class="grid grid-cols-2 gap-3">
                    <div wire:key="edit-quantity-{{ $editing->id }}-{{ $editUnit }}">
                        <x-number-stepper
                            wire:model.live.debounce.300ms="editQuantity"
                            :label="__('foods.picker.quantity')"
                            :step="$editUnit === 'g' ? 10 : 0.5"
                            :error="$errors->first('editQuantity') ?: ($editError && $editError['field'] === 'editQuantity' ? $editError['message'] : null)"
                        />
                    </div>
                    <x-select
                        wire:model.live="editUnit"
                        :label="__('foods.picker.unit')"
                        :options="$editUnits"
                        :selected="$editUnit"
                        :error="$errors->first('editUnit') ?: ($editError && $editError['field'] === 'editUnit' ? $editError['message'] : null)"
                    />
                </div>

                <x-segmented wire:model="editMeal" showLabel :label="__('today.edit.meal')" :options="$mealOptions" />

                <x-input wire:model="editDate" type="date" :label="__('today.edit.date')" max="{{ $maxDate }}" />

                @if ($editing->weighed_cooked)
                    <p class="text-sm text-ink-muted">{{ __('today.edit.cooked_note') }}</p>
                @endif

                @if ($editing->food === null && $editing->recipe === null)
                    <x-alert variant="info">{{ __('today.edit.source_deleted') }}</x-alert>
                @endif

                @php $entryError = $errors->first('editEntry') ?: ($editError && $editError['field'] === 'editEntry' ? $editError['message'] : null); @endphp
                @if ($entryError)
                    <x-alert variant="danger">{{ $entryError }}</x-alert>
                @endif

                <div wire:loading.class="opacity-60" wire:target="editQuantity, editUnit" class="transition-opacity duration-150">
                    <p class="mb-1.5 text-sm font-medium text-ink">{{ __('foods.picker.preview') }}</p>
                    @include('livewire.foods.partials.nutrient-tiles', ['nutrients' => $editPreview])
                </div>
            @else
                <x-skeleton shape="text" :lines="4" />
            @endif
        </form>
        <x-slot:footer>
            <x-button variant="danger-ghost" icon="trash" wire:click="confirmDelete" class="sm:me-auto">{{ __('common.delete') }}</x-button>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'edit-entry')">{{ __('common.cancel') }}</x-button>
            <x-button type="submit" form="edit-entry-form" loading="saveEdit" icon="check">{{ __('common.save') }}</x-button>
        </x-slot:footer>
    </x-bottom-sheet>

    {{-- Delete confirmation --}}
    <x-modal name="delete-entry" size="sm" :title="__('today.delete.title')">
        <p class="text-ink-muted">{{ __('today.delete.text', ['name' => $deleting?->description ?? '']) }}</p>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'delete-entry')">{{ __('common.cancel') }}</x-button>
            <x-button variant="danger" wire:click="delete">{{ __('common.delete') }}</x-button>
        </x-slot:footer>
    </x-modal>
</div>
