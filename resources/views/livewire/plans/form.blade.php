@php
    use App\View\Format;

    $g = __('common.units.g');
    $context = \App\Livewire\Plans\Form::PICKER_CONTEXT;
    $foodSheet = \App\Livewire\Plans\Form::FOOD_SHEET;
    $recipeSheet = \App\Livewire\Plans\Form::RECIPE_SHEET;
    $copySheet = \App\Livewire\Plans\Form::COPY_SHEET;
    $dayRows = collect($rows)->where('day', $day['index']);
@endphp

<div>
    <form wire:submit="save" novalidate class="space-y-4">
        {{-- Basics --}}
        <x-card>
            <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_11rem_8rem]">
                <x-input wire:model="name" :label="__('plans.fields.name')" maxlength="255" required />
                <x-input wire:model.live="startsOn" type="date" :label="__('plans.fields.starts_on')" required />
                <x-input wire:model.live.debounce.400ms="days" type="number" inputmode="numeric" min="1" max="14" :label="__('plans.fields.days')" :hint="__('plans.fields.days_hint')" />
            </div>
        </x-card>

        {{-- Days: horizontal selector on phones, week grid on desktop --}}
        <div class="-mx-gutter flex gap-2 overflow-x-auto px-gutter pb-1 [scrollbar-width:none] lg:mx-0 lg:grid lg:grid-cols-7 lg:overflow-visible lg:px-0" role="tablist" aria-label="{{ __('plans.days') }}">
            @foreach ($dayList as $d)
                @php $selected = $d['index'] === $day['index']; @endphp
                <button type="button" role="tab" aria-selected="{{ $selected ? 'true' : 'false' }}" wire:click="selectDay({{ $d['index'] }})" wire:key="day-tab-{{ $d['index'] }}"
                        @class([
                            'flex min-h-touch w-20 shrink-0 flex-col items-center justify-center gap-0.5 rounded-control border px-2 py-2 text-center transition-colors duration-150 lg:w-auto',
                            'border-accent bg-accent-soft text-accent-ink' => $selected,
                            'border-line-strong bg-surface text-ink-muted hover:text-ink' => ! $selected,
                        ])>
                    <span class="text-xs">@date($d['date'], 'day')</span>
                    <span class="num text-base font-semibold">@num($d['date']->day)</span>
                    @if ($d['count'] > 0)
                        <span class="num text-xs {{ $d['over'] ? 'font-semibold text-danger-ink' : '' }}">
                            @if ($d['over'])<span class="sr-only">{{ __('plans.over_carbs') }}</span>@endif{{ Format::number($d['totals']->netCarbs(), 1) }} {{ $g }}
                        </span>
                    @else
                        <span class="text-xs">—</span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
            {{-- Meals of the active day --}}
            <div class="min-w-0 space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-ink">@date($day['date'], 'weekday')</h2>
                    <x-button type="button" variant="ghost" size="sm" icon="list" wire:click="openCopy">{{ __('plans.copy_day') }}</x-button>
                </div>

                @foreach ($meals as $meal)
                    @php $mealRows = $dayRows->where('meal', $meal->value); @endphp
                    <x-card :title="$meal->label()" wire:key="meal-{{ $day['index'] }}-{{ $meal->value }}">
                        <x-slot:actions>
                            <x-button type="button" variant="ghost" size="sm" icon="plus" wire:click="openFoodPicker('{{ $meal->value }}')">{{ __('plans.add_food') }}</x-button>
                            <x-button type="button" variant="ghost" size="sm" icon="book" wire:click="openRecipePicker('{{ $meal->value }}')">{{ __('plans.add_recipe') }}</x-button>
                        </x-slot:actions>

                        @if ($mealRows->isEmpty())
                            <p class="text-sm text-ink-subtle">{{ __('plans.meal_empty') }}</p>
                        @else
                            <ul class="-my-3 divide-y divide-line">
                                @foreach ($mealRows as $row)
                                    @php
                                        $i = $row['index'];
                                        $rowError = $errors->first("items.$i.quantity") ?: $errors->first("items.$i.unit");
                                    @endphp
                                    <li class="py-3" wire:key="item-{{ $row['key'] }}">
                                        <div class="flex items-start gap-1">
                                            <p class="min-w-0 flex-1 pt-2 font-medium leading-snug text-ink">
                                                {{ $row['name'] }}
                                                @if ($row['is_recipe'])<x-badge class="ms-1">{{ __('plans.recipe') }}</x-badge>@endif
                                            </p>
                                            <x-button type="button" variant="danger-ghost" size="sm" square icon="trash" wire:click="removeItem('{{ $row['key'] }}')"
                                                      aria-label="{{ __('plans.remove_item', ['name' => $row['name']]) }}" />
                                        </div>
                                        <div class="mt-2 grid grid-cols-[7rem_minmax(0,1fr)] gap-2">
                                            <x-input wire:model.live.debounce.400ms="items.{{ $i }}.quantity" type="text" inputmode="decimal" autocomplete="off"
                                                     class="num text-center [direction:ltr]" :error="false"
                                                     aria-label="{{ __('plans.fields.quantity') }} — {{ $row['name'] }}" />
                                            <x-select wire:model.live="items.{{ $i }}.unit" :options="$row['units']" :error="false"
                                                      aria-label="{{ __('plans.fields.unit') }} — {{ $row['name'] }}" />
                                        </div>
                                        @if ($rowError)
                                            <p class="mt-1.5 flex items-start gap-1.5 text-sm font-medium text-danger-ink" role="alert"><x-icon name="alert" size="sm" class="mt-0.5" /><span>{{ $rowError }}</span></p>
                                        @elseif ($row['nutrients'])
                                            <p class="mt-1.5 text-sm text-ink-muted">
                                                <span class="num">{{ Format::number($row['nutrients']->calories) }}</span> {{ __('common.units.kcal') }}
                                                · {{ __('common.macros.net_carbs') }} <span class="num">{{ Format::number($row['nutrients']->netCarbs(), 1) }}</span> {{ $g }}
                                            </p>
                                        @else
                                            <p class="mt-1.5 text-sm text-warning-ink">{{ __('plans.row_not_counted') }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>
                @endforeach
            </div>

            {{-- Day totals vs targets --}}
            <div class="min-w-0 lg:sticky lg:top-[calc(var(--spacing-topbar)+1.5rem)]">
                <x-card :title="__('plans.day_totals')">
                    <div wire:loading.class="opacity-60" wire:target="items, days, startsOn, addFood, addRecipe, removeItem, copyDay" class="transition-opacity duration-150" aria-live="polite">
                        @if ($day['count'] === 0)
                            <p class="text-sm text-ink-muted">{{ __('plans.day_empty') }}</p>
                        @else
                            @include('livewire.plans.partials.day-totals', ['totals' => $day['totals'], 'targets' => $targets])
                            @if ($day['over'])
                                <x-alert variant="danger" class="mt-3">{{ __('plans.over_carbs_text') }}</x-alert>
                            @endif
                            @unless ($day['totals']->isComplete())
                                <p class="mt-3 text-sm text-ink-muted">{{ __('common.at_least_hint') }}</p>
                            @endunless
                        @endif
                    </div>
                </x-card>
            </div>
        </div>

        @error('items')<x-alert variant="danger">{{ $message }}</x-alert>@enderror

        <div class="sticky bottom-tabbar z-10 -mx-gutter flex gap-2 border-t border-line bg-canvas px-gutter py-3 lg:static lg:mx-0 lg:justify-end lg:border-0 lg:bg-transparent lg:p-0">
            <x-button variant="secondary" :href="$editing ? route('plans.show', $planId) : route('plans.index')" navigate class="flex-1 lg:flex-none">{{ __('common.cancel') }}</x-button>
            <x-button type="submit" loading="save" icon="check" class="flex-1 lg:min-w-40 lg:flex-none">{{ __('common.save') }}</x-button>
        </div>
    </form>

    {{-- Food picker (shared) --}}
    <x-bottom-sheet :name="$foodSheet" :title="__('plans.add_food')" size="lg">
        @if ($pickerAvailable)
            <livewire:foods.picker :context="$context" :with-quantity="true" wire:key="plan-food-picker" />
        @else
            <x-alert variant="info">{{ __('recipes.picker_unavailable') }}</x-alert>
        @endif
    </x-bottom-sheet>

    {{-- Recipe picker --}}
    <x-bottom-sheet :name="$recipeSheet" :title="__('plans.add_recipe')">
        @if ($recipeList->isEmpty())
            <x-empty-state icon="book" compact :title="__('plans.no_recipes_title')" :text="__('plans.no_recipes_text')">
                <x-button variant="secondary" :href="route('recipes.create')">{{ __('recipes.create') }}</x-button>
            </x-empty-state>
        @else
            <ul class="-mx-2 divide-y divide-line">
                @foreach ($recipeList as $recipe)
                    <li wire:key="recipe-option-{{ $recipe->id }}">
                        <button type="button" wire:click="addRecipe({{ $recipe->id }})" class="flex min-h-touch w-full items-center gap-2 rounded-control px-2 py-3 text-start transition-colors duration-150 hover:bg-surface-2">
                            <span class="min-w-0 flex-1 font-medium text-ink">{{ $recipe->name }}</span>
                            <x-icon name="plus" size="sm" class="text-ink-subtle" />
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-bottom-sheet>

    {{-- Copy day --}}
    <x-bottom-sheet :name="$copySheet" :title="__('plans.copy_day')" :description="__('plans.copy_day_hint')" size="sm">
        <x-select wire:model="copyTo" :label="__('plans.copy_to')" :options="$copyOptions" :selected="$copyTo" />
        <x-slot:footer>
            <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', '{{ $copySheet }}')">{{ __('common.cancel') }}</x-button>
            <x-button type="button" wire:click="copyDay" icon="check">{{ __('plans.copy_day') }}</x-button>
        </x-slot:footer>
    </x-bottom-sheet>
</div>
