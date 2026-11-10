{{--
    Dialog. Centered on every screen size; pass `sheet` (or use <x-bottom-sheet>) for a
    panel that rises from the bottom edge on phones and is centered from sm up.
    Focus is trapped while open, ESC and the backdrop close it, focus returns to the opener.

    Props
      name         string        unique id used by the open/close events (required)
      title        string|null   heading (also the accessible name)
      description  string|null   secondary line under the title
      size         sm|md|lg      max width from sm up — default md
      sheet        bool          bottom-sheet presentation on phones
      dismissible  bool          allow ESC / backdrop / close button (default true)
      show         bool          open on first render

    Slots
      default, footer (action row — put the primary action last)

    Open / close
      Alpine     x-on:click="$dispatch('open-modal', 'delete-entry')"     $dispatch('close-modal', 'delete-entry')
      Livewire   $this->dispatch('open-modal', name: 'delete-entry');     $this->dispatch('close-modal', name: 'delete-entry');
      Bound      <x-modal name="edit" wire:model="showEdit" …>   (boolean Livewire property)
      $dispatch('close-modal') without a name closes every open modal.

    Usage
      <x-modal name="delete-entry" :title="__('log.delete_title')" size="sm">
          <p class="text-ink-muted">{{ __('log.delete_text') }}</p>
          <x-slot:footer>
              <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'delete-entry')">{{ __('common.cancel') }}</x-button>
              <x-button variant="danger" wire:click="delete">{{ __('common.delete') }}</x-button>
          </x-slot:footer>
      </x-modal>
--}}
@props([
    'name',
    'title' => null,
    'description' => null,
    'size' => 'md',
    'sheet' => false,
    'dismissible' => true,
    'show' => false,
])

@php
    $sizes = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-lg', 'lg' => 'sm:max-w-2xl'];
    $titleId = 'modal-'.$name.'-title';
@endphp

<div
    x-data="modal({ name: @js($name), open: @js((bool) $show), dismissible: @js((bool) $dismissible) })"
    x-modelable="open"
    x-on:open-modal.window="onOpen($event)"
    x-on:close-modal.window="onClose($event)"
    x-on:keydown.escape.window="dismiss()"
    class="contents"
    {{ $attributes->whereStartsWith(['wire:model', 'x-model']) }}
>
    <div
        x-show="open"
        x-cloak
        role="dialog"
        aria-modal="true"
        @if ($title) aria-labelledby="{{ $titleId }}" @endif
        {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model'])->class([
            'fixed inset-0 z-50 flex justify-center',
            'items-end sm:items-center sm:p-4' => $sheet,
            'items-center p-4' => ! $sheet,
        ]) }}
    >
        {{-- backdrop --}}
        <div
            x-show="open"
            x-transition.opacity.duration.150ms
            x-on:click="dismiss()"
            class="fixed inset-0 bg-overlay"
            aria-hidden="true"
        ></div>

        {{-- panel --}}
        <div
            x-show="open"
            x-trap.noscroll.inert="open"
            x-transition:enter="transition duration-150 ease-out"
            x-transition:enter-start="opacity-0 {{ $sheet ? 'translate-y-4 sm:translate-y-0' : '' }}"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @class([
                'relative flex max-h-[90dvh] w-full flex-col bg-surface text-ink shadow-overlay',
                $sizes[$size] ?? $sizes['md'],
                'rounded-t-sheet sm:rounded-sheet' => $sheet,
                'max-w-lg rounded-sheet' => ! $sheet,
            ])
        >
            @if ($sheet)
                <div class="flex justify-center pt-2.5 sm:hidden" aria-hidden="true">
                    <span class="h-1 w-9 rounded-full bg-surface-3"></span>
                </div>
            @endif

            @if ($title || $dismissible)
                <div class="flex items-start justify-between gap-3 ps-5 pe-3 pt-4">
                    <div class="min-w-0 pt-1.5">
                        @if ($title)
                            <h2 id="{{ $titleId }}" class="text-lg font-semibold leading-snug">{{ $title }}</h2>
                        @endif
                        @if ($description)
                            <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
                        @endif
                    </div>
                    @if ($dismissible)
                        <button
                            type="button"
                            x-on:click="open = false"
                            class="flex size-touch shrink-0 items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink"
                            aria-label="{{ __('common.close') }}"
                        >
                            <x-icon name="x" />
                        </button>
                    @endif
                </div>
            @endif

            <div @class(['min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pt-3', 'pb-5' => isset($footer), 'pb-[calc(1.25rem+env(safe-area-inset-bottom,0px))] sm:pb-5' => ! isset($footer)])>
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex flex-col-reverse gap-2 border-t border-line px-5 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom,0px))] sm:flex-row sm:justify-end sm:pb-4">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
