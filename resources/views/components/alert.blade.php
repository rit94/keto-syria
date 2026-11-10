{{--
    Inline message that stays in the page flow (form-level errors, medical notices,
    "data incomplete" explanations). For transient feedback use toasts (see x-toaster).

    Props
      variant      info|success|warning|danger   default info
      title        string|null
      icon         string|null   override the default icon
      dismissible  bool          adds a close button (Alpine)

    Usage
      <x-alert variant="warning" :title="__('profile.medical_title')">{{ __('profile.medical_text') }}</x-alert>
      @if (session('status')) <x-alert variant="success">{{ session('status') }}</x-alert> @endif
--}}
@props(['variant' => 'info', 'title' => null, 'icon' => null, 'dismissible' => false])

@php
    $variants = [
        'info' => ['bg-info-soft', 'text-info-ink', 'info'],
        'success' => ['bg-success-soft', 'text-success-ink', 'check'],
        'warning' => ['bg-warning-soft', 'text-warning-ink', 'alert'],
        'danger' => ['bg-danger-soft', 'text-danger-ink', 'alert'],
    ];
    [$bg, $tone, $defaultIcon] = $variants[$variant] ?? $variants['info'];
@endphp

<div
    @if ($dismissible) x-data="{ shown: true }" x-show="shown" @endif
    role="{{ $variant === 'danger' ? 'alert' : 'status' }}"
    {{ $attributes->class(['flex items-start gap-3 rounded-card p-3.5 text-[0.9375rem] leading-relaxed text-ink', $bg]) }}
>
    <x-icon :name="$icon ?? $defaultIcon" class="mt-0.5 {{ $tone }}" />

    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold {{ $tone }}">{{ $title }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div>{{ $slot }}</div>
        @endif
    </div>

    @if ($dismissible)
        <button
            type="button"
            x-on:click="shown = false"
            class="-my-2 -me-2 flex size-touch shrink-0 items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:text-ink"
            aria-label="{{ __('common.close') }}"
        >
            <x-icon name="x" size="sm" />
        </button>
    @endif
</div>
