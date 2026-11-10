{{--
    Four compact figures for one Nutrients value object: kcal · net carbs · protein · fat.
    Engine output only — this partial formats, it never calculates.

    $n      App\Domain\Nutrition\Nutrients
    $class  string|null  extra classes on the wrapper
--}}
@php
    use App\View\Format;

    $g = __('common.units.g');
    $figures = [
        ['label' => __('common.macros.calories'), 'value' => Format::number($n->calories), 'unit' => __('common.units.kcal'), 'dot' => 'bg-macro-kcal'],
        ['label' => __('common.macros.net_carbs'), 'value' => Format::number($n->netCarbs(), 1), 'unit' => $g, 'dot' => 'bg-macro-carbs'],
        ['label' => __('common.macros.protein'), 'value' => Format::number($n->protein, 1), 'unit' => $g, 'dot' => 'bg-macro-protein'],
        ['label' => __('common.macros.fat'), 'value' => Format::number($n->fat, 1), 'unit' => $g, 'dot' => 'bg-macro-fat'],
    ];
@endphp

<dl class="grid grid-cols-4 gap-2 {{ $class ?? '' }}">
    @foreach ($figures as $figure)
        <div class="min-w-0">
            <dt class="flex items-center gap-1 text-xs text-ink-muted">
                <span class="size-1.5 shrink-0 rounded-full {{ $figure['dot'] }}" aria-hidden="true"></span>
                <span class="truncate">{{ $figure['label'] }}</span>
            </dt>
            <dd class="mt-0.5 truncate text-sm text-ink-muted">
                <span class="num text-[0.9375rem] font-semibold text-ink">{{ $figure['value'] }}</span>
                <span class="text-xs">{{ $figure['unit'] }}</span>
            </dd>
        </div>
    @endforeach
</dl>
