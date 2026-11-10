{{--
    Small status label.

    Props
      variant     neutral|accent|success|warning|danger|info   default neutral
      icon        string|null   leading icon name
      estimate    bool          preset: the "≈ Estimate" badge for is_estimate values
      confidence  App\Enums\Confidence|string|null   preset: data-accuracy badge (high/medium/low)

    Usage
      <x-badge variant="accent">{{ $food->category->label() }}</x-badge>
      @if ($food->is_estimate) <x-badge estimate /> @endif
      <x-badge :confidence="$food->confidence" />
--}}
@props(['variant' => 'neutral', 'icon' => null, 'estimate' => false, 'confidence' => null])

@php
    $label = null;
    $title = null;

    if ($estimate) {
        $variant = 'warning';
        $label = '≈ '.__('common.estimate');
        $title = __('common.estimate_hint');
    } elseif ($confidence !== null) {
        $level = $confidence instanceof \BackedEnum ? $confidence->value : (string) $confidence;
        $variant = ['high' => 'success', 'medium' => 'neutral', 'low' => 'warning'][$level] ?? 'neutral';
        $icon = $level === 'high' ? 'check' : null;
        $label = __('enums.confidence.'.$level);
        $title = __('common.confidence');
    }

    $variants = [
        'neutral' => 'bg-surface-2 text-ink-muted',
        'accent' => 'bg-accent-soft text-accent-ink',
        'success' => 'bg-success-soft text-success-ink',
        'warning' => 'bg-warning-soft text-warning-ink',
        'danger' => 'bg-danger-soft text-danger-ink',
        'info' => 'bg-info-soft text-info-ink',
    ];
@endphp

<span
    @if ($title) title="{{ $title }}" @endif
    {{ $attributes->class([
        'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium leading-5',
        $variants[$variant] ?? $variants['neutral'],
    ]) }}
>
    @if ($icon)<x-icon :name="$icon" size="xs" />@endif
    {{ $label ?? $slot }}
</span>
