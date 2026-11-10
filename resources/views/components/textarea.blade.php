{{--
    Multi-line text input. Wraps itself in x-field when `label` is given.

    Props
      label, hint, error, optional   passed to x-field
      name   string|null   defaults to the wire:model property
      id     string|null   defaults to name
      rows   int           default 3

    Usage
      <x-textarea wire:model="note" :label="__('log.note')" rows="3" optional />
--}}
@props(['label' => null, 'hint' => null, 'error' => null, 'optional' => false, 'name' => null, 'id' => null, 'rows' => 3])

@php
    $name ??= $attributes->wire('model')->value() ?: null;
    $id ??= $name ? str_replace(['.', '[', ']'], '-', $name) : null;
    $error ??= $name ? $errors->first($name) : null;
    $describedBy = $id ? ($error ? "$id-error" : ($hint ? "$id-hint" : null)) : null;
@endphp

<x-field :label="$label" :for="$id" :error="$error" :hint="$hint" :optional="$optional">
    <textarea
        rows="{{ $rows }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class('control resize-y py-2.5 leading-relaxed') }}
    >{{ $slot }}</textarea>
</x-field>
