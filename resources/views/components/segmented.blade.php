{{--
    Segmented control — a compact single-choice switch (2–5 options). Native radios
    underneath, so wire:model / x-model / plain forms all work and arrow keys move
    the selection.

    Props
      options  array         value => label   (required)
      name     string|null   radio group name; defaults to the wire:model property
      label    string|null   accessible group label (visually hidden unless showLabel)
      showLabel bool
      selected mixed         checked value when not bound with wire:model
      block    bool          stretch to full width (default true)

    Usage
      <x-segmented wire:model.live="meal" :label="__('log.meal')"
                   :options="collect(App\Enums\Meal::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all()" />
--}}
@props(['options', 'name' => null, 'label' => null, 'showLabel' => false, 'selected' => null, 'block' => true])

@php
    $name ??= $attributes->wire('model')->value() ?: 'segmented-'.\Illuminate\Support\Str::random(6);
    $idBase = str_replace(['.', '[', ']'], '-', $name);
@endphp

<fieldset {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model'])->class(['min-w-0', 'w-full' => $block]) }}>
    @if ($label)
        <legend @class(['mb-1.5 text-sm font-medium text-ink', 'sr-only' => ! $showLabel])>{{ $label }}</legend>
    @endif

    <div @class(['gap-1 rounded-control bg-surface-2 p-1', 'flex w-full' => $block, 'inline-flex' => ! $block])>
        @foreach ($options as $value => $optionLabel)
            <label
                @class([
                    'relative flex min-h-9 cursor-pointer items-center justify-center rounded-[calc(var(--radius-control)-4px)] px-2 text-center text-sm font-medium leading-tight',
                    'text-ink-muted transition-colors duration-150 hover:text-ink',
                    'has-checked:bg-surface has-checked:text-ink has-checked:shadow-card',
                    'has-focus-visible:outline-2 has-focus-visible:outline-offset-1 has-focus-visible:outline-focus',
                    "after:absolute after:-inset-y-1 after:inset-x-0 after:content-['']",
                    'flex-1' => $block,
                ])
            >
                <input
                    type="radio"
                    class="sr-only"
                    name="{{ $name }}"
                    id="{{ $idBase }}-{{ $value }}"
                    value="{{ $value }}"
                    @checked($selected !== null && (string) $selected === (string) $value)
                    {{ $attributes->whereStartsWith(['wire:model', 'x-model']) }}
                >
                <span>{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>
</fieldset>
