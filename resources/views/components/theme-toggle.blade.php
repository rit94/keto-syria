{{--
    Light/dark toggle. The choice is stored in localStorage('theme') and applied by the
    inline script in layouts/partials/head (window.KetoTheme). Already in both layouts.

    Props
      labelled  bool  menu-row presentation with the "Appearance" label — default false (icon button)

    Usage  <x-theme-toggle />
--}}
@props(['labelled' => false])

<button
    type="button"
    onclick="window.KetoTheme && window.KetoTheme.toggle()"
    {{ $attributes->class([
        'flex items-center gap-3 transition-colors duration-150 hover:bg-surface-2',
        'size-touch justify-center rounded-full text-ink-muted hover:text-ink' => ! $labelled,
        'min-h-touch w-full rounded-control px-3 text-[0.9375rem] font-medium text-ink' => $labelled,
    ]) }}
    aria-label="{{ __('app.theme.toggle') }}"
>
    <x-icon name="moon" class="dark:hidden {{ $labelled ? 'text-ink-muted' : '' }}" />
    <x-icon name="sun" class="hidden dark:block {{ $labelled ? 'text-ink-muted' : '' }}" />
    @if ($labelled)
        <span class="flex-1 text-start">{{ __('app.theme.label') }}</span>
        <span class="text-sm text-ink-muted dark:hidden">{{ __('app.theme.light') }}</span>
        <span class="hidden text-sm text-ink-muted dark:inline">{{ __('app.theme.dark') }}</span>
    @endif
</button>
