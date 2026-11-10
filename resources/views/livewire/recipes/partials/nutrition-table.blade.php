{{--
    Nutrition table: one row per nutrient, one column per basis.
    Engine output only — this partial formats, it never calculates.

    $columns  array<string label, App\Domain\Nutrition\Nutrients|null>   (null columns are skipped)
    $caption  string   accessible table caption
--}}
@php
    use App\Domain\Nutrition\Nutrients;
    use App\View\Format;

    $columns = array_filter($columns);
    $g = __('common.units.g');

    $rows = [
        ['label' => __('common.macros.calories'), 'unit' => __('common.units.kcal'), 'get' => fn (Nutrients $n) => $n->calories, 'decimals' => 0, 'strong' => true, 'missing' => null],
        ['label' => __('common.macros.net_carbs'), 'unit' => $g, 'get' => fn (Nutrients $n) => $n->netCarbs(), 'decimals' => 1, 'strong' => true, 'missing' => null],
        ['label' => __('common.macros.carbs'), 'unit' => $g, 'get' => fn (Nutrients $n) => $n->carbs, 'decimals' => 1, 'strong' => false, 'missing' => null],
        ['label' => __('common.macros.fiber'), 'unit' => $g, 'get' => fn (Nutrients $n) => $n->fiber, 'decimals' => 1, 'strong' => false, 'missing' => Nutrients::FIBER],
        ['label' => __('common.macros.protein'), 'unit' => $g, 'get' => fn (Nutrients $n) => $n->protein, 'decimals' => 1, 'strong' => false, 'missing' => null],
        ['label' => __('common.macros.fat'), 'unit' => $g, 'get' => fn (Nutrients $n) => $n->fat, 'decimals' => 1, 'strong' => false, 'missing' => null],
        ['label' => __('recipes.nutrition.saturated_fat'), 'unit' => $g, 'get' => fn (Nutrients $n) => $n->saturatedFat, 'decimals' => 1, 'strong' => false, 'missing' => Nutrients::SATURATED_FAT],
    ];
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <caption class="sr-only">{{ $caption }}</caption>
        <thead>
            <tr class="border-b border-line text-xs text-ink-muted">
                <th scope="col" class="py-2 pe-2 text-start font-medium"><span class="sr-only">{{ __('recipes.nutrition.nutrient') }}</span></th>
                @foreach ($columns as $label => $nutrients)
                    <th scope="col" class="px-1.5 py-2 text-end font-medium last:pe-0">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @foreach ($rows as $row)
                <tr>
                    <th scope="row" class="py-2 pe-2 text-start font-normal {{ $row['strong'] ? 'font-medium text-ink' : 'text-ink-muted' }}">
                        {{ $row['label'] }} <span class="text-xs text-ink-subtle">({{ $row['unit'] }})</span>
                    </th>
                    @foreach ($columns as $nutrients)
                        @php
                            $value = $row['get']($nutrients);
                            $atLeast = $row['missing'] !== null && $value !== null && $nutrients->isMissing($row['missing']);
                        @endphp
                        <td class="px-1.5 py-2 text-end last:pe-0">
                            @if ($atLeast)<span class="sr-only">{{ __('common.at_least') }}</span>@endif
                            <span class="num {{ $row['strong'] ? 'font-semibold text-ink' : 'text-ink' }}" @if ($atLeast) title="{{ __('common.at_least_hint') }}" @endif>{{ $atLeast ? '≥ ' : '' }}{{ Format::number($value, $row['decimals']) }}</span>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
