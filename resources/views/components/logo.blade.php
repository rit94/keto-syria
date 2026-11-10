{{--
    Brand mark (olive sprig) with the localized app name.

    Props
      size      sm|md|lg   default md
      markOnly  bool       hide the name

    Usage  <x-logo />   <x-logo size="lg" />
--}}
@props(['size' => 'md', 'markOnly' => false])

@php
    $sizes = [
        'sm' => ['size-7', 'text-base'],
        'md' => ['size-9', 'text-lg'],
        'lg' => ['size-12', 'text-2xl'],
    ];
    [$mark, $text] = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->class('inline-flex items-center gap-2.5') }}>
    <span class="flex {{ $mark }} shrink-0 items-center justify-center rounded-[30%] bg-accent text-on-accent">
        <svg viewBox="0 0 24 24" class="size-[62%]" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 19c0-8 5-13 14-14 0 9-5 14-14 14Z" fill="currentColor" fill-opacity="0.28" />
            <path d="M5 19 13.500 10.500" />
        </svg>
    </span>
    @unless ($markOnly)
        <span class="{{ $text }} font-semibold leading-none text-ink">{{ __('app.name') }}</span>
    @endunless
</span>
