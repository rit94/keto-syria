{{--
    Loading placeholder. Pair with wire:loading / lazy components.

    Props
      shape  text|title|circle|block|row   default text
               text   — `lines` lines of body text (last one shorter)
               title  — one heading line
               circle — avatar / ring; size with class (default size-12)
               block  — free rectangle; size with class (e.g. class="h-40")
               row    — a list-row placeholder (circle + two lines)
      lines  int   number of lines for shape="text" (default 3)

    Usage
      <div wire:loading.delay> <x-skeleton shape="row" /> <x-skeleton shape="row" /> </div>
      <x-skeleton shape="block" class="h-40" />
--}}
@props(['shape' => 'text', 'lines' => 3])

@php
    $widths = ['w-full', 'w-11/12', 'w-4/5', 'w-full', 'w-3/4'];
@endphp

@if ($shape === 'circle')
    <div aria-hidden="true" {{ $attributes->class('skeleton size-12 !rounded-full') }}></div>
@elseif ($shape === 'block')
    <div aria-hidden="true" {{ $attributes->class('skeleton !rounded-card w-full') }}></div>
@elseif ($shape === 'title')
    <div aria-hidden="true" {{ $attributes->class('skeleton h-6 w-2/5') }}></div>
@elseif ($shape === 'row')
    <div aria-hidden="true" {{ $attributes->class('flex items-center gap-3 px-4 py-3') }}>
        <div class="skeleton size-10 shrink-0 !rounded-full"></div>
        <div class="flex-1 space-y-2">
            <div class="skeleton h-4 w-1/2"></div>
            <div class="skeleton h-3 w-1/3"></div>
        </div>
        <div class="skeleton h-4 w-10"></div>
    </div>
@else
    <div aria-hidden="true" {{ $attributes->class('space-y-2.5') }}>
        @for ($i = 0; $i < $lines; $i++)
            <div class="skeleton h-4 {{ $i === $lines - 1 && $lines > 1 ? 'w-3/5' : $widths[$i % count($widths)] }}"></div>
        @endfor
    </div>
@endif
