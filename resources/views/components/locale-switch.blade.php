{{--
    Language switch (ar ⇄ en). Posts to route('locale.switch'), which stores the choice in
    the session and on the signed-in user, then returns to the same page. Already in both layouts.

    Props
      labelled  bool  menu-row presentation with the "Language" label — default false (compact pill)

    Usage  <x-locale-switch />
--}}
@props(['labelled' => false])

@php
    $other = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $otherName = __('app.locales.'.$other);
@endphp

<form method="POST" action="{{ route('locale.switch') }}" {{ $attributes->class(['w-full' => $labelled]) }}>
    @csrf
    <input type="hidden" name="locale" value="{{ $other }}">

    @if ($labelled)
        <button type="submit" class="flex min-h-touch w-full items-center gap-3 rounded-control px-3 text-[0.9375rem] font-medium text-ink transition-colors duration-150 hover:bg-surface-2">
            <x-icon name="globe" class="text-ink-muted" />
            <span class="flex-1 text-start">{{ __('app.language') }}</span>
            <span class="text-sm text-ink-muted" lang="{{ $other }}">{{ $otherName }}</span>
        </button>
    @else
        <button
            type="submit"
            class="flex min-h-touch items-center gap-1.5 rounded-full px-3 text-sm font-medium text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink"
            aria-label="{{ __('app.switch_language', ['language' => $otherName]) }}"
        >
            <x-icon name="globe" />
            <span lang="{{ $other }}">{{ $otherName }}</span>
        </button>
    @endif
</form>
