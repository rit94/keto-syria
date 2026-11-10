{{--
    Native select with a custom chevron. Wraps itself in x-field when `label` is given.

    Props
      label, hint, error, optional   passed to x-field
      name         string|null   defaults to the wire:model property
      id           string|null   defaults to name
      options      array         value => label (or pass <option>s in the slot)
      placeholder  string|null   disabled first option
      selected     mixed         selected value when not bound with wire:model

    Usage
      <x-select wire:model="unit" :label="__('log.unit')" :options="$units" />
      <x-select wire:model="meal" :label="__('log.meal')" :placeholder="__('log.choose_meal')">
          @foreach (App\Enums\Meal::cases() as $meal)
              <option value="{{ $meal->value }}">{{ $meal->label() }}</option>
          @endforeach
      </x-select>
--}}
@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'optional' => false,
    'name' => null,
    'id' => null,
    'options' => [],
    'placeholder' => null,
    'selected' => null,
])

@php
    $name ??= $attributes->wire('model')->value() ?: null;
    $id ??= $name ? str_replace(['.', '[', ']'], '-', $name) : null;
    $error ??= $name ? $errors->first($name) : null;
    $describedBy = $id ? ($error ? "$id-error" : ($hint ? "$id-hint" : null)) : null;
@endphp

<x-field :label="$label" :for="$id" :error="$error" :hint="$hint" :optional="$optional">
    <div class="relative">
        <select
            @if ($name) name="{{ $name }}" @endif
            @if ($id) id="{{ $id }}" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class('control appearance-none pe-10') }}
        >
            @if ($placeholder)
                <option value="" disabled @selected($selected === null || $selected === '')>{{ $placeholder }}</option>
            @endif

            @foreach ($options as $value => $optionLabel)
                <option value="{{ $value }}" @selected((string) $value === (string) $selected && $selected !== null)>{{ $optionLabel }}</option>
            @endforeach

            {{ $slot }}
        </select>

        <span class="pointer-events-none absolute inset-y-0 end-0 flex w-10 items-center justify-center text-ink-muted">
            <x-icon name="chevron-down" size="sm" />
        </span>
    </div>
</x-field>
