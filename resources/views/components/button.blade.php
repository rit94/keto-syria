{{--
    Button / link-button.

    Props
      variant   primary|secondary|ghost|danger|danger-ghost|subtle   default primary
      size      sm|md|lg            default md (44 px tall; sm is 40 px with an extended hit area)
      icon      string|null         leading icon name (see x-icon)
      iconEnd   string|null         trailing icon name
      href      string|null         render an <a> instead of <button>
      type      button|submit|reset default button
      block     bool                full width
      square    bool                icon-only (give it an aria-label)
      loading   bool|string         Livewire loading state. Automatic for wire:click buttons
                                    (target = the clicked action). For a submit button inside
                                    <form wire:submit="save"> pass loading="save".
                                    loading=false turns it off.
      navigate  bool                with href: add wire:navigate (default false)

    Usage
      <x-button wire:click="save">{{ __('common.save') }}</x-button>
      <x-button type="submit" loading="login" block>{{ __('auth.login.submit') }}</x-button>
      <x-button variant="ghost" icon="plus" :href="route('foods.create')">{{ __('common.add') }}</x-button>
      <x-button variant="secondary" square icon="edit" aria-label="{{ __('common.edit') }}" />
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconEnd' => null,
    'href' => null,
    'type' => 'button',
    'block' => false,
    'square' => false,
    'loading' => null,
    'navigate' => false,
])

@php
    $variants = [
        'primary' => 'bg-accent text-on-accent hover:bg-accent-hover',
        'secondary' => 'border border-line-strong bg-surface text-ink hover:bg-surface-2',
        'ghost' => 'text-accent-ink hover:bg-accent-soft',
        'danger' => 'bg-danger text-on-danger hover:bg-danger-hover',
        'danger-ghost' => 'text-danger-ink hover:bg-danger-soft',
        'subtle' => 'bg-surface-2 text-ink hover:bg-surface-3',
    ];

    $sizes = [
        'sm' => $square ? 'size-10 text-sm' : 'min-h-10 px-3 text-sm',
        'md' => $square ? 'size-touch' : 'min-h-touch px-4 text-[0.9375rem]',
        'lg' => $square ? 'size-13' : 'min-h-13 px-6 text-base',
    ];

    $iconSize = $size === 'sm' ? 'sm' : 'md';

    // Livewire loading target: explicit string, or the wire:click action.
    $target = is_string($loading) ? $loading : $attributes->whereStartsWith('wire:click')->first();
    $hasLoading = ! $href && $loading !== false && ($loading === true || filled($target));

    $classes = implode(' ', [
        'group/button relative inline-flex items-center justify-center gap-2 rounded-control font-semibold leading-tight',
        'select-none transition-colors duration-150',
        'disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50',
        // extend the hit area of the small size to 44 px
        $size === 'sm' ? "after:absolute after:-inset-0.5 after:content-['']" : '',
        $block ? 'w-full' : '',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
        @if ($slot->isNotEmpty())<span>{{ $slot }}</span>@endif
        @if ($iconEnd)<x-icon :name="$iconEnd" :size="$iconSize" />@endif
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class($classes) }}
        @if ($hasLoading)
            {{-- not `disabled`: a disabled button drops focus, which breaks focus return from modals --}}
            wire:loading.attr="aria-busy"
            wire:loading.class="pointer-events-none"
            @if ($target) wire:target="{{ $target }}" @endif
        @endif
    >
        <span
            class="inline-flex items-center justify-center gap-2"
            @if ($hasLoading)
                wire:loading.class="opacity-0"
                @if ($target) wire:target="{{ $target }}" @endif
            @endif
        >
            @if ($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
            @if ($slot->isNotEmpty())<span>{{ $slot }}</span>@endif
            @if ($iconEnd)<x-icon :name="$iconEnd" :size="$iconSize" />@endif
        </span>

        @if ($hasLoading)
            <span
                class="absolute inset-0 items-center justify-center"
                wire:loading.flex
                @if ($target) wire:target="{{ $target }}" @endif
            >
                <x-icon name="spinner" :size="$iconSize" />
                <span class="sr-only">{{ __('common.loading') }}</span>
            </span>
        @endif
    </button>
@endif
