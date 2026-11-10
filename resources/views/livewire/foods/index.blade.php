@php
    $chip = 'inline-flex min-h-9 shrink-0 items-center whitespace-nowrap rounded-full border px-3.5 text-sm font-medium transition-colors duration-150';
    $chipOn = 'border-accent bg-accent-soft text-accent-ink';
    $chipOff = 'border-line-strong bg-surface text-ink-muted hover:text-ink';
@endphp

<div class="space-y-4">
    <x-tabs wire:model.live="tab" :tabs="['all' => __('foods.tabs.all'), 'mine' => ['label' => __('foods.tabs.mine'), 'count' => $myCount]]" :active="$tab" :label="__('foods.title')" />

    <x-input wire:model.live.debounce.300ms="query" type="search" icon="search" autocomplete="off" :placeholder="__('foods.search_placeholder')" aria-label="{{ __('foods.search_label') }}" />

    <div class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]" role="group" aria-label="{{ __('foods.category_filter') }}">
        <button type="button" wire:click="$set('category', '')" aria-pressed="{{ $category === '' ? 'true' : 'false' }}" class="{{ $chip }} {{ $category === '' ? $chipOn : $chipOff }}">{{ __('foods.all_categories') }}</button>
        @foreach ($categories as $case)
            <button type="button" wire:click="$set('category', '{{ $case->value }}')" aria-pressed="{{ $category === $case->value ? 'true' : 'false' }}" class="{{ $chip }} {{ $category === $case->value ? $chipOn : $chipOff }}">{{ $case->label() }}</button>
        @endforeach
    </div>

    <div wire:loading.class="opacity-60" class="min-h-64 transition-opacity duration-150">
        @if ($foods->isEmpty())
            @if ($mine && $myCount === 0)
                <x-empty-state icon="book" :title="__('foods.mine_empty_title')" :text="__('foods.mine_empty_text')">
                    <x-button icon="plus" :href="route('foods.create')" navigate>{{ __('foods.create_custom') }}</x-button>
                </x-empty-state>
            @else
                <x-empty-state icon="search" :title="__('foods.picker.nothing_title')" :text="__('foods.picker.nothing_text')">
                    <x-button icon="plus" :href="route('foods.create', array_filter(['name' => trim($query)]))" navigate>{{ __('foods.create_custom') }}</x-button>
                </x-empty-state>
            @endif
        @else
            <ul class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
                @foreach ($foods as $food)
                    <li wire:key="food-{{ $food->id }}">
                        <a href="{{ route('foods.show', $food) }}" wire:navigate class="flex items-center gap-2 px-4 py-3 transition-colors duration-150 hover:bg-surface-2">
                            @include('livewire.foods.partials.food-summary', ['food' => $food])
                            <x-icon name="chevron-end" size="sm" class="shrink-0 text-ink-subtle" />
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-center text-xs text-ink-subtle">{{ __('foods.per_100g') }}</p>
        @endif
    </div>

    <x-button variant="secondary" icon="plus" block :href="route('foods.create')" navigate>{{ __('foods.create_custom') }}</x-button>
</div>
