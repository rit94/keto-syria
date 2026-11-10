{{--
    Quantity entry: − / typed number / +. Arabic-Indic digits typed on an Arabic
    keyboard are converted to Western digits. Decrement sits at the inline start.

    Props
      label, hint, error, optional   passed to x-field
      name    string|null   defaults to the wire:model property
      id      string|null   defaults to name
      min     float         default 0
      max     float|null
      step    float         amount added by the buttons (default 1); typing any decimal is allowed
      unit    string|null   unit text shown inside the control (e.g. __('common.units.g'))
      value   mixed         initial value when not bound with wire:model

    Usage
      <x-number-stepper wire:model.live.debounce.300ms="quantity" :label="__('log.quantity')" :step="0.5" :unit="$unitLabel" />
--}}
@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'optional' => false,
    'name' => null,
    'id' => null,
    'min' => 0,
    'max' => null,
    'step' => 1,
    'unit' => null,
    'value' => null,
])

@php
    $name ??= $attributes->wire('model')->value() ?: null;
    $id ??= $name ? str_replace(['.', '[', ']'], '-', $name) : null;
    $error ??= $name ? $errors->first($name) : null;
    $describedBy = $id ? ($error ? "$id-error" : ($hint ? "$id-hint" : null)) : null;
    $config = ['min' => (float) $min, 'max' => $max === null ? null : (float) $max, 'step' => (float) $step];
    $buttonClasses = 'flex size-touch shrink-0 items-center justify-center rounded-[calc(var(--radius-control)-3px)] text-ink transition-colors duration-150 hover:bg-surface-3';
@endphp

<x-field :label="$label" :for="$id" :error="$error" :hint="$hint" :optional="$optional">
    <div
        x-data="numberStepper(@js($config))"
        @class([
            'flex items-center gap-1 rounded-control border bg-surface-2 p-0.5 transition-colors duration-150',
            'focus-within:outline-2 focus-within:outline-offset-1 focus-within:outline-focus',
            'border-danger' => $error,
            'border-line-strong' => ! $error,
        ])
    >
        <button type="button" class="{{ $buttonClasses }}" x-on:click="decrement()" aria-label="{{ __('common.decrease') }}">
            <x-icon name="minus" />
        </button>

        <div class="flex min-w-0 flex-1 items-baseline justify-center gap-1.5">
            <input
                x-ref="input"
                x-on:input="normalize()"
                type="text"
                inputmode="decimal"
                autocomplete="off"
                @if ($name) name="{{ $name }}" @endif
                @if ($id) id="{{ $id }}" @endif
                @if ($value !== null) value="{{ $value }}" @endif
                @if ($error) aria-invalid="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                {{ $attributes->class([
                    'h-touch min-w-0 bg-transparent text-lg font-semibold text-ink outline-none [direction:ltr]',
                    'w-16 text-end' => $unit,
                    'w-full text-center' => ! $unit,
                ]) }}
            >
            @if ($unit)
                <span class="shrink-0 text-sm text-ink-muted">{{ $unit }}</span>
            @endif
        </div>

        <button type="button" class="{{ $buttonClasses }}" x-on:click="increment()" aria-label="{{ __('common.increase') }}">
            <x-icon name="plus" />
        </button>
    </div>
</x-field>
