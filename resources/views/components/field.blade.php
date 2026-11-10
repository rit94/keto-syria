{{--
    Field wrapper: label + control + hint + error. x-input / x-textarea / x-select /
    x-number-stepper use it automatically when given a `label`; use it directly to wrap
    custom controls.

    Props
      label     string|null
      for       string|null   id of the control
      name      string|null   error-bag key; the first error for it is shown
      error     string|null   explicit error message (overrides the error bag)
      hint      string|null   helper text under the control
      optional  bool          show an "optional" marker next to the label

    Ids used for aria-describedby:  {for}-hint  and  {for}-error

    Usage
      <x-field :label="__('foods.brand')" for="brand" name="brand" optional>
          <input id="brand" class="control" wire:model="brand">
      </x-field>
--}}
@props(['label' => null, 'for' => null, 'name' => null, 'error' => null, 'hint' => null, 'optional' => false])

@php
    $error ??= $name ? $errors->first($name) : null;
@endphp

<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="flex items-baseline justify-between gap-2 text-sm font-medium text-ink">
            <span>{{ $label }}</span>
            @if ($optional)
                <span class="text-xs font-normal text-ink-subtle">{{ __('common.optional') }}</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint && ! $error)
        <p @if ($for) id="{{ $for }}-hint" @endif class="text-sm text-ink-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p @if ($for) id="{{ $for }}-error" @endif class="flex items-start gap-1.5 text-sm font-medium text-danger-ink" role="alert">
            <x-icon name="alert" size="sm" class="mt-0.5" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
