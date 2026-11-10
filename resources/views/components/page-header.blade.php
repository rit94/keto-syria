{{--
    In-page header: optional back link, title, subtitle and actions. The layout's top bar
    already shows the page title — use this for sub-pages (detail / edit screens) and for
    section headings inside a page (level="2").

    Props
      title     string        (required)
      subtitle  string|null
      back      string|null   URL for the back link (chevron flips with direction)
      backLabel string|null   accessible label for the back link (default common.back)
      level     1|2           heading level / size (default 1)

    Slot
      actions — buttons at the inline end

    Usage
      <x-page-header :title="$recipe->name" :subtitle="__('recipes.servings', ['count' => $recipe->servings])" :back="route('recipes.index')">
          <x-slot:actions>
              <x-button variant="secondary" icon="edit" :href="route('recipes.edit', $recipe)">{{ __('common.edit') }}</x-button>
          </x-slot:actions>
      </x-page-header>

      <x-page-header level="2" :title="App\Enums\Meal::Breakfast->label()">
          <x-slot:actions><x-button variant="ghost" size="sm" icon="plus" wire:click="add('breakfast')">{{ __('common.add') }}</x-button></x-slot:actions>
      </x-page-header>
--}}
@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => null, 'level' => 1])

@php
    $heading = (int) $level === 2 ? 'h2' : 'h1';
@endphp

<div {{ $attributes->class(['flex items-center gap-2', 'mb-4' => (int) $level === 1, 'mb-2' => (int) $level === 2]) }}>
    @if ($back)
        <a
            href="{{ $back }}"
            wire:navigate
            class="-ms-2.5 flex size-touch shrink-0 items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink"
            aria-label="{{ $backLabel ?? __('common.back') }}"
        >
            <x-icon name="chevron-start" size="lg" />
        </a>
    @endif

    <div class="min-w-0 flex-1">
        <{{ $heading }} @class([
            'truncate font-semibold leading-tight text-ink',
            'text-2xl' => (int) $level === 1,
            'text-base' => (int) $level === 2,
        ])>{{ $title }}</{{ $heading }}>
        @if ($subtitle)
            <p class="mt-0.5 truncate text-sm text-ink-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 items-center gap-1.5">{{ $actions }}</div>
    @endisset
</div>
