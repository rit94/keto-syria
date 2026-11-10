@php
    use App\View\Format;

    $g = __('common.units.g');
    $kcal = __('common.units.kcal');
    $logSheet = \App\Livewire\Recipes\Show::LOG_SHEET;
    $deleteModal = \App\Livewire\Recipes\Show::DELETE_MODAL;
    $unitLabel = $logUnit === 'g' ? $g : __('units.serving');
@endphp

<x-slot:actions>
    <x-button variant="ghost" square icon="edit" :href="route('recipes.edit', $recipe)" navigate aria-label="{{ __('common.edit') }}" />
</x-slot:actions>

<div class="space-y-4">
    {{-- Summary --}}
    <x-card>
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-muted">
            <span>{{ __('recipes.servings_label') }}: <span class="num font-medium text-ink">{{ Format::number($recipe->servings, 2) }}</span></span>
            @if ($estimate)
                <x-badge estimate />
            @endif
            @if ($recipe->origin === 'ai')
                <x-badge variant="accent" icon="sparkles">{{ __('recipes.from_coach') }}</x-badge>
            @endif
        </div>

        @if ($recipe->description)
            <p class="mt-2 whitespace-pre-line text-ink-muted">{{ $recipe->description }}</p>
        @endif

        <p class="mt-4 text-xs text-ink-subtle">{{ __('recipes.per_serving') }}</p>
        @include('livewire.recipes.partials.macro-line', ['n' => $base->perServing, 'class' => 'mt-1'])

        <div class="mt-4 flex flex-wrap gap-2 [&>*]:whitespace-nowrap">
            <x-button icon="plus" x-on:click="$dispatch('open-modal', '{{ $logSheet }}')">{{ __('recipes.log.action') }}</x-button>
            <x-button variant="secondary" wire:click="duplicate">{{ __('recipes.duplicate') }}</x-button>
            <x-button variant="danger-ghost" icon="trash" x-on:click="$dispatch('open-modal', '{{ $deleteModal }}')">{{ __('common.delete') }}</x-button>
        </div>
    </x-card>

    {{-- Ingredients + serving adjuster --}}
    <x-card :title="__('recipes.ingredients')">
        <div class="grid items-end gap-3 sm:grid-cols-[minmax(0,16rem)_auto]">
            <x-number-stepper wire:model.live.debounce.300ms="servings" :label="__('recipes.adjust.label')" :min="0.5" :step="1" />
            @if ($isScaled)
                <x-button variant="ghost" wire:click="resetServings">{{ __('recipes.adjust.reset', ['count' => Format::number($recipe->servings, 2)]) }}</x-button>
            @endif
        </div>
        <p class="mt-1.5 text-sm text-ink-muted">{{ __('recipes.adjust.hint') }}</p>

        <ul class="mt-3 divide-y divide-line border-t border-line" wire:loading.class="opacity-60" wire:target="servings, resetServings">
            @foreach ($ingredients as $ingredient)
                <li class="flex items-start justify-between gap-3 py-3" wire:key="ingredient-{{ $loop->index }}">
                    <div class="min-w-0">
                        <p class="font-medium leading-snug text-ink">{{ $ingredient['food']->displayName() }}</p>
                        <p class="mt-0.5 text-sm text-ink-muted">
                            <span class="num">{{ Format::number($ingredient['contribution']->calories) }}</span> {{ $kcal }}
                            · {{ __('common.macros.net_carbs') }} <span class="num">{{ Format::number($ingredient['contribution']->netCarbs(), 1) }}</span> {{ $g }}
                            · {{ __('common.macros.protein') }} <span class="num">{{ Format::number($ingredient['contribution']->protein, 1) }}</span> {{ $g }}
                            · {{ __('common.macros.fat') }} <span class="num">{{ Format::number($ingredient['contribution']->fat, 1) }}</span> {{ $g }}
                        </p>
                    </div>
                    <div class="shrink-0 text-end">
                        <p class="text-ink"><span class="num font-semibold">{{ Format::number($ingredient['quantity'], 2) }}</span> <span class="text-sm text-ink-muted">{{ $ingredient['unit'] }}</span></p>
                        @if ($ingredient['unit_key'] !== 'g')
                            <p class="text-xs text-ink-subtle"><span class="num">{{ Format::number($ingredient['grams'], 1) }}</span> {{ $g }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </x-card>

    {{-- Nutrition --}}
    <x-card :title="__('recipes.nutrition.title')">
        @if ($estimate)
            <x-slot:actions><x-badge estimate /></x-slot:actions>
        @endif

        <div wire:loading.class="opacity-60" wire:target="servings, resetServings">
            @include('livewire.recipes.partials.nutrition-table', [
                'caption' => __('recipes.nutrition.title'),
                'columns' => [
                    __('recipes.nutrition.per_serving') => $nutrition->perServing,
                    __('recipes.nutrition.for_servings', ['count' => Format::number($servingsShown, 2)]) => $nutrition->total,
                    __('recipes.nutrition.per_100g') => $nutrition->per100g,
                ],
            ])
        </div>

        <p class="mt-3 text-sm text-ink-muted">
            @if ($base->finishedWeightIsMeasured)
                {{ __('recipes.nutrition.cooked_weight') }} <span class="num font-medium text-ink">{{ Format::number($base->finishedWeightGrams, 1) }}</span> {{ $g }}
                · {{ __('recipes.nutrition.raw_weight') }} <span class="num font-medium text-ink">{{ Format::number($base->rawWeightGrams, 1) }}</span> {{ $g }}
            @else
                {{ __('recipes.nutrition.per_100g_raw_note') }}
            @endif
        </p>

        @unless ($base->total->isComplete())
            <x-alert variant="warning" class="mt-3" :title="__('recipes.nutrition.incomplete_title')">{{ __('recipes.nutrition.incomplete_text') }}</x-alert>
        @endunless
    </x-card>

    @if ($recipe->instructions)
        <x-card :title="__('recipes.fields.instructions')">
            <p class="whitespace-pre-line leading-relaxed text-ink">{{ $recipe->instructions }}</p>
        </x-card>
    @endif

    {{-- Log this --}}
    <x-bottom-sheet :name="$logSheet" :title="__('recipes.log.title')" :description="$recipe->name">
        <form wire:submit="log" id="log-recipe-form" class="space-y-4" novalidate>
            <x-segmented wire:model.live="logMeal" :label="__('recipes.log.meal')" showLabel :options="$meals" />

            <x-input wire:model="logDate" type="date" :label="__('recipes.log.date')" />

            <div class="grid grid-cols-2 gap-3">
                <div wire:key="log-quantity-{{ $logUnit }}">
                    <x-number-stepper wire:model.live.debounce.300ms="logQuantity" :label="__('recipes.log.quantity')"
                                      :step="$logUnit === 'g' ? 10 : 0.5" :min="0" />
                </div>
                <x-segmented wire:model.live="logUnit" :label="__('recipes.log.unit')" showLabel
                             :options="\App\Livewire\Recipes\Support\Amount::recipeUnitOptions()" />
            </div>

            @error('logUnit')
                <x-alert variant="danger">{{ $message }}</x-alert>
            @else
                @if ($previewError)
                    <x-alert variant="warning">{{ $previewError }}</x-alert>
                @elseif ($logUnit === 'g' && ! $base->finishedWeightIsMeasured)
                    <x-alert variant="info">{{ __('recipes.log.grams_raw_note') }}</x-alert>
                @endif
            @enderror

            @if ($preview)
                <div wire:loading.class="opacity-60" wire:target="logQuantity, logUnit" aria-live="polite">
                    <p class="text-sm font-medium text-ink">
                        {{ __('recipes.log.preview') }}
                        <span class="font-normal text-ink-muted">· <span class="num">{{ Format::number(\App\Livewire\Recipes\Support\Amount::parse($logQuantity), 2) }}</span> {{ $unitLabel }}</span>
                    </p>
                    @include('livewire.recipes.partials.macro-line', ['n' => $preview, 'class' => 'mt-2'])
                </div>
            @endif
        </form>

        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', '{{ $logSheet }}')">{{ __('common.cancel') }}</x-button>
            <x-button type="submit" form="log-recipe-form" loading="log" icon="check" :disabled="$preview === null">{{ __('recipes.log.submit') }}</x-button>
        </x-slot:footer>
    </x-bottom-sheet>

    {{-- Delete --}}
    <x-modal :name="$deleteModal" :title="__('recipes.delete_title')" size="sm">
        <p class="text-ink-muted">{{ __('recipes.delete_text', ['name' => $recipe->name]) }}</p>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', '{{ $deleteModal }}')">{{ __('common.cancel') }}</x-button>
            <x-button variant="danger" wire:click="delete">{{ __('common.delete') }}</x-button>
        </x-slot:footer>
    </x-modal>
</div>
