<x-slot:actions>
    <x-button variant="ghost" square icon="plus" :href="route('recipes.create')" navigate aria-label="{{ __('recipes.create') }}" />
</x-slot:actions>

<div class="space-y-4">
    @if ($total === 0)
        <x-empty-state icon="book" :title="__('recipes.empty_title')" :text="__('recipes.empty_text')">
            <x-button icon="plus" :href="route('recipes.create')" navigate>{{ __('recipes.create') }}</x-button>
        </x-empty-state>
    @else
        <div class="flex items-center gap-2">
            <div class="min-w-0 flex-1">
                <x-input wire:model.live.debounce.300ms="query" type="search" icon="search"
                         :placeholder="__('recipes.search_placeholder')" aria-label="{{ __('recipes.search_placeholder') }}" />
            </div>
            <x-button icon="plus" :href="route('recipes.create')" navigate class="hidden sm:inline-flex">{{ __('recipes.create') }}</x-button>
        </div>

        <div wire:loading.delay wire:target="query" class="w-full space-y-3" aria-hidden="true">
            <x-skeleton shape="row" />
            <x-skeleton shape="row" />
        </div>

        <div wire:loading.delay.remove wire:target="query">
            @if ($cards->isEmpty())
                <x-empty-state icon="search" :title="__('recipes.no_results_title')" :text="__('recipes.no_results_text', ['query' => $query])" compact>
                    <x-button variant="secondary" wire:click="$set('query', '')">{{ __('recipes.clear_search') }}</x-button>
                </x-empty-state>
            @else
                <ul class="grid gap-3 sm:grid-cols-2" aria-label="{{ __('recipes.title') }}">
                    @foreach ($cards as $card)
                        @php
                            $recipe = $card['recipe'];
                        @endphp
                        <li wire:key="recipe-{{ $recipe->id }}">
                            <x-card as="a" href="{{ route('recipes.show', $recipe) }}" wire:navigate interactive padding="sm" class="block h-full">
                                <div class="flex items-start justify-between gap-3">
                                    <h2 class="min-w-0 text-base font-semibold leading-snug text-ink">{{ $recipe->name }}</h2>
                                    <x-icon name="chevron-end" size="sm" class="mt-1 text-ink-subtle" />
                                </div>

                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-muted">
                                    <span>{{ __('recipes.servings_label') }}: <span class="num font-medium text-ink">{{ \App\View\Format::number($recipe->servings, 2) }}</span></span>
                                    @if ($card['estimate'])
                                        <x-badge estimate />
                                    @endif
                                    @if ($recipe->origin === 'ai')
                                        <x-badge variant="accent" icon="sparkles">{{ __('recipes.from_coach') }}</x-badge>
                                    @endif
                                </div>

                                @if ($card['nutrition'])
                                    <p class="mt-3 text-xs text-ink-subtle">{{ __('recipes.per_serving') }}</p>
                                    @include('livewire.recipes.partials.macro-line', ['n' => $card['nutrition']->perServing, 'class' => 'mt-1'])
                                @endif
                            </x-card>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
