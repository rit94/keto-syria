{{--
    Toast host. Rendered once by the layouts — do not add it to pages.

    Shows (a) toasts flashed to the session with App\View\Toast (or a `status` flash) and
    (b) `toast` browser events:

      PHP, before a redirect     App\View\Toast::success(__('foods.saved'));   // also ::error ::warning ::info
      Livewire, same page        use App\Livewire\Concerns\Toasts;  $this->toast(__('log.saved'));  $this->toast($msg, 'error');
                                 (equivalent to $this->dispatch('toast', message: $msg, type: 'error'))
      Alpine / JS                $dispatch('toast', { message: '…', type: 'info' })

    Types: success (default) | error | warning | info. Errors stay 7 s, others 4 s.
--}}
@php
    $icons = ['success' => 'check', 'error' => 'alert', 'warning' => 'alert', 'info' => 'info'];
    $tones = [
        'success' => 'text-success-ink',
        'error' => 'text-danger-ink',
        'warning' => 'text-warning-ink',
        'info' => 'text-info-ink',
    ];
@endphp

<div
    x-data="toaster(@js(\App\View\Toast::pending()))"
    x-on:toast.window="push($event.detail)"
    class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex flex-col items-center gap-2 px-4 pt-[calc(env(safe-area-inset-top,0px)+0.75rem)] lg:items-end lg:px-6"
    role="status"
    aria-live="polite"
>
    <template x-for="toast in toasts" x-bind:key="toast.id">
        <div
            x-transition:enter="transition duration-150 ease-out"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-card border border-line bg-surface py-2 ps-4 pe-1.5 text-ink shadow-raised"
        >
            <span class="mt-2.5 shrink-0">
                @foreach ($icons as $type => $icon)
                    <span x-show="toast.type === '{{ $type }}'" class="{{ $tones[$type] }}">
                        <x-icon :name="$icon" />
                        <span class="sr-only">{{ __('common.toast.'.$type) }}:</span>
                    </span>
                @endforeach
            </span>
            <p class="min-w-0 flex-1 py-2.5 text-[0.9375rem] leading-snug" x-text="toast.message"></p>
            <button
                type="button"
                x-on:click="dismiss(toast.id)"
                class="flex size-touch shrink-0 items-center justify-center rounded-full text-ink-subtle transition-colors duration-150 hover:bg-surface-2 hover:text-ink"
                aria-label="{{ __('common.toast.dismiss') }}"
            >
                <x-icon name="x" size="sm" />
            </button>
        </div>
    </template>
</div>
