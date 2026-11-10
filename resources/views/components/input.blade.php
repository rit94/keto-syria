{{--
    Text input. Wraps itself in x-field when `label` is given.

    Props
      label, hint, error, optional   passed to x-field
      name      string|null   defaults to the wire:model property; used for id + error lookup
      id        string|null   defaults to name
      type      string        default text. email/password/number/tel/url are typed LTR
                              (and right-aligned in RTL) automatically.
      icon      string|null   leading icon name
      suffix    string|null   trailing unit text, e.g. __('common.units.g')

    Usage
      <x-input wire:model="email" type="email" :label="__('auth.email')" autocomplete="email" />
      <x-input wire:model.live.debounce.300ms="query" type="search" icon="search" :placeholder="__('common.search')" />
      <x-input wire:model="weight" type="number" inputmode="decimal" step="0.1" :suffix="__('common.units.kg')" :label="__('progress.weight')" />
--}}
@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'optional' => false,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'icon' => null,
    'suffix' => null,
])

@php
    $name ??= $attributes->wire('model')->value() ?: null;
    $id ??= $name ? str_replace(['.', '[', ']'], '-', $name) : null;
    $error ??= $name ? $errors->first($name) : null;
    $isLtrType = in_array($type, ['email', 'password', 'number', 'tel', 'url'], true);
    $describedBy = $id ? ($error ? "$id-error" : ($hint ? "$id-hint" : null)) : null;
@endphp

<x-field :label="$label" :for="$id" :error="$error" :hint="$hint" :optional="$optional">
    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 start-0 flex w-11 items-center justify-center text-ink-subtle">
                <x-icon :name="$icon" />
            </span>
        @endif

        <input
            type="{{ $type }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($id) id="{{ $id }}" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class([
                'control',
                // LTR-typed inputs keep direction:ltr, so their logical padding must be flipped by hand in RTL.
                'ps-11' => $icon && ! $isLtrType,
                'pe-14' => $suffix && ! $isLtrType,
                'ltr:ps-11 rtl:pe-11' => $icon && $isLtrType,
                'ltr:pe-14 rtl:ps-14' => $suffix && $isLtrType,
                '[direction:ltr] rtl:text-right' => $isLtrType,
            ]) }}
        >

        @if ($suffix)
            <span class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3.5 text-sm text-ink-muted">{{ $suffix }}</span>
        @endif
    </div>
</x-field>
