@php
    use App\View\Format;

    $g = __('common.units.g');
    $kcal = __('common.units.kcal');
    $sheet = \App\Livewire\Recipes\Form::PICKER_SHEET;
    $context = \App\Livewire\Recipes\Form::PICKER_CONTEXT;
    $openPicker = "\$dispatch('food-picker-reset', { context: '$context' }); \$dispatch('open-modal', '$sheet')";
@endphp

<div>
    <form wire:submit="save" novalidate>
      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start lg:gap-6">
        <div class="min-w-0 space-y-4">
            {{-- Basics --}}
            <x-card>
                <div class="space-y-4">
                    <x-input wire:model="name" :label="__('recipes.fields.name')" :placeholder="__('recipes.fields.name_placeholder')" maxlength="255" required />

                    <x-textarea wire:model="description" :label="__('recipes.fields.description')" rows="2" optional />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-number-stepper wire:model.live.debounce.300ms="servings" :label="__('recipes.fields.servings')" :min="1" :step="1" />

                        <x-input wire:model.live.debounce.400ms="cookedWeight" type="text" inputmode="decimal" autocomplete="off"
                                 class="num [direction:ltr] rtl:text-right"
                                 :label="__('recipes.fields.cooked_weight')" :suffix="$g" optional
                                 :hint="__('recipes.fields.cooked_weight_hint')" />
                    </div>
                </div>
            </x-card>

            {{-- Ingredients --}}
            <x-card :title="__('recipes.ingredients')">
                <x-slot:actions>
                    <x-button variant="ghost" size="sm" icon="plus" x-on:click="{{ $openPicker }}">{{ __('common.add') }}</x-button>
                </x-slot:actions>

                @error('ingredients')
                    <x-alert variant="danger" class="mb-3">{{ $message }}</x-alert>
                @enderror

                @if ($rows === [])
                    <x-empty-state icon="list" compact :title="__('recipes.no_ingredients_title')" :text="__('recipes.no_ingredients_text')">
                        <x-button icon="plus" x-on:click="{{ $openPicker }}">{{ __('recipes.add_ingredient') }}</x-button>
                    </x-empty-state>
                @else
                    <ol class="-my-3 divide-y divide-line">
                        @foreach ($rows as $row)
                            @php
                                $i = $row['index'];
                                $name = $row['food']?->displayName() ?? __('recipes.unknown_food');
                                $rowError = $errors->first("ingredients.$i.quantity") ?: $errors->first("ingredients.$i.unit") ?: $errors->first("ingredients.$i.food_id");
                            @endphp
                            <li wire:key="ingredient-{{ $row['key'] }}" class="py-3">
                                <div class="flex items-start gap-1">
                                    <div class="min-w-0 flex-1 pt-2">
                                        <p class="font-medium leading-snug text-ink">{{ $name }}</p>
                                        @if ($row['food']?->is_estimate)
                                            <div class="mt-1"><x-badge estimate /></div>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center">
                                        <x-button variant="ghost" size="sm" square icon="chevron-up" wire:click="moveIngredient('{{ $row['key'] }}', -1)"
                                                  :disabled="$loop->first" aria-label="{{ __('recipes.move_up', ['name' => $name]) }}" />
                                        <x-button variant="ghost" size="sm" square icon="chevron-down" wire:click="moveIngredient('{{ $row['key'] }}', 1)"
                                                  :disabled="$loop->last" aria-label="{{ __('recipes.move_down', ['name' => $name]) }}" />
                                        <x-button variant="danger-ghost" size="sm" square icon="trash" wire:click="removeIngredient('{{ $row['key'] }}')"
                                                  aria-label="{{ __('recipes.remove_ingredient', ['name' => $name]) }}" />
                                    </div>
                                </div>

                                <div class="mt-2 grid grid-cols-[7rem_minmax(0,1fr)] gap-2">
                                    <x-input wire:model.live.debounce.400ms="ingredients.{{ $i }}.quantity" type="text" inputmode="decimal" autocomplete="off"
                                             class="num text-center [direction:ltr]" :error="false"
                                             aria-label="{{ __('recipes.fields.quantity') }} — {{ $name }}" />
                                    <x-select wire:model.live="ingredients.{{ $i }}.unit" :options="$row['units']" :error="false"
                                              aria-label="{{ __('recipes.fields.unit') }} — {{ $name }}" />
                                </div>

                                @if ($rowError)
                                    <p class="mt-1.5 flex items-start gap-1.5 text-sm font-medium text-danger-ink" role="alert">
                                        <x-icon name="alert" size="sm" class="mt-0.5" />
                                        <span>{{ $rowError }}</span>
                                    </p>
                                @elseif ($row['contribution'])
                                    <p class="mt-1.5 text-sm text-ink-muted">
                                        <span class="num">{{ Format::number($row['grams'], 1) }}</span> {{ $g }}
                                        · <span class="num">{{ Format::number($row['contribution']->calories) }}</span> {{ $kcal }}
                                        · {{ __('common.macros.net_carbs') }} <span class="num">{{ Format::number($row['contribution']->netCarbs(), 1) }}</span> {{ $g }}
                                    </p>
                                @else
                                    <p class="mt-1.5 text-sm text-warning-ink">{{ __('recipes.row_not_counted') }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>

            {{-- Method --}}
            <x-card>
                <x-textarea wire:model="instructions" :label="__('recipes.fields.instructions')" rows="6" optional />
            </x-card>
        </div>

        {{-- Live nutrition (engine output) --}}
        <div class="min-w-0 space-y-4 lg:sticky lg:top-[calc(var(--spacing-topbar)+1.5rem)]">
            <x-card :title="__('recipes.nutrition.title')">
                @if ($estimate)
                    <x-slot:actions><x-badge estimate /></x-slot:actions>
                @endif

                <div wire:loading.class="opacity-60" wire:target="servings, cookedWeight, ingredients, addIngredient, removeIngredient" class="transition-opacity duration-150" aria-live="polite">
                    @if ($nutrition)
                        @include('livewire.recipes.partials.nutrition-table', [
                            'caption' => __('recipes.nutrition.title'),
                            'columns' => [
                                __('recipes.nutrition.per_serving') => $nutrition->perServing,
                                __('recipes.nutrition.whole') => $nutrition->total,
                                __('recipes.nutrition.per_100g') => $nutrition->per100g,
                            ],
                        ])

                        <p class="mt-3 text-sm text-ink-muted">
                            {{ __('recipes.nutrition.raw_weight') }} <span class="num font-medium text-ink">{{ Format::number($nutrition->rawWeightGrams, 1) }}</span> {{ $g }}
                            @if ($nutrition->finishedWeightIsMeasured)
                                · {{ __('recipes.nutrition.cooked_weight') }} <span class="num font-medium text-ink">{{ Format::number($nutrition->finishedWeightGrams, 1) }}</span> {{ $g }}
                            @endif
                        </p>
                        @unless ($nutrition->finishedWeightIsMeasured)
                            <p class="mt-1 text-sm text-ink-muted">{{ __('recipes.nutrition.per_100g_raw_note') }}</p>
                        @endunless

                        @unless ($nutrition->total->isComplete())
                            <x-alert variant="warning" class="mt-3" :title="__('recipes.nutrition.incomplete_title')">{{ __('recipes.nutrition.incomplete_text') }}</x-alert>
                        @endunless

                        @if ($skipped > 0)
                            <x-alert variant="warning" class="mt-3">{{ __('recipes.nutrition.rows_skipped') }}</x-alert>
                        @endif
                    @else
                        <p class="text-sm text-ink-muted">{{ __('recipes.nutrition.empty') }}</p>
                    @endif
                </div>
            </x-card>
        </div>
      </div>

        <div class="sticky bottom-tabbar z-10 -mx-gutter mt-4 flex gap-2 border-t border-line bg-canvas px-gutter py-3 lg:static lg:mx-0 lg:mt-6 lg:justify-end lg:border-0 lg:bg-transparent lg:p-0">
            <x-button variant="secondary" :href="$editing ? route('recipes.show', $recipeId) : route('recipes.index')" navigate class="flex-1 lg:flex-none">{{ __('common.cancel') }}</x-button>
            <x-button type="submit" loading="save" icon="check" class="flex-1 lg:flex-none lg:min-w-40">{{ __('common.save') }}</x-button>
        </div>
    </form>

    <x-bottom-sheet :name="$sheet" :title="__('recipes.add_ingredient')" size="lg">
        @if ($pickerAvailable)
            <livewire:foods.picker :context="$context" :with-quantity="true" wire:key="recipe-ingredient-picker" />
        @else
            <x-alert variant="info">{{ __('recipes.picker_unavailable') }}</x-alert>
        @endif
    </x-bottom-sheet>
</div>
