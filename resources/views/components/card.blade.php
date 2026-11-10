{{--
    Surface container.

    Props
      as        string        element tag (div|section|article|a|button|li) — default div
      padding   none|sm|md|lg default md
      muted     bool          sunken surface without border (for secondary groupings)
      interactive bool        hover/focus affordance (use with as="a" or as="button")
      title     string|null   optional heading row
      subtitle  string|null

    Slots
      default, actions (end of the heading row), footer (separated by a hairline)

    Usage
      <x-card :title="__('today.summary')">…</x-card>
      <x-card as="a" href="{{ route('recipes.show', $recipe) }}" interactive padding="sm">…</x-card>
      <x-card padding="none"> <x-list-row …/> <x-list-row …/> </x-card>
--}}
@props([
    'as' => 'div',
    'padding' => 'md',
    'muted' => false,
    'interactive' => false,
    'title' => null,
    'subtitle' => null,
])

@php
    $paddings = ['none' => '', 'sm' => 'p-3', 'md' => 'p-4 sm:p-5', 'lg' => 'p-5 sm:p-6'];
    $pad = $paddings[$padding] ?? $paddings['md'];
    $headPad = $padding === 'none' ? 'px-4 pt-4 sm:px-5' : '';
@endphp

<{{ $as }} {{ $attributes->class([
    'block rounded-card text-start',
    'bg-surface-2' => $muted,
    'border border-line bg-surface shadow-card' => ! $muted,
    'transition-colors duration-150 hover:border-line-strong' => $interactive,
    $pad,
]) }}>
    @if ($title || isset($actions))
        <div class="{{ $headPad }} mb-3 flex items-start justify-between gap-3">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-base font-semibold leading-snug text-ink">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-ink-muted">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-1">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}

    @isset($footer)
        <div @class(['mt-4 border-t border-line pt-3', 'px-4 pb-4 sm:px-5' => $padding === 'none'])>{{ $footer }}</div>
    @endisset
</{{ $as }}>
