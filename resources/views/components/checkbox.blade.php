{{--
    Checkbox with label (44 px row).

    Props
      label  string|null   or use the slot for rich label content
      name   string|null   defaults to the wire:model property
      id     string|null   defaults to name

    Usage
      <x-checkbox wire:model="remember" :label="__('auth.remember')" />
--}}
@props(['label' => null, 'name' => null, 'id' => null])

@php
    $name ??= $attributes->wire('model')->value() ?: null;
    $id ??= $name ? str_replace(['.', '[', ']'], '-', $name) : null;
@endphp

<label @if ($id) for="{{ $id }}" @endif class="flex min-h-touch cursor-pointer items-center gap-3 text-[0.9375rem] text-ink">
    <input
        type="checkbox"
        @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        {{ $attributes->class('size-5 shrink-0 rounded-md border-line-strong accent-accent') }}
    >
    <span>{{ $label ?? $slot }}</span>
</label>
