{{--
    Engine nutrition numbers, display only.

    $n         array  Nutrients::toArray() (calories, protein_g, net_carbs_g, fat_g, fiber_g, missing)
    $caption   string|null
    $small     bool   smaller tiles (per item)
    Rounding happens here via Format::number(); nothing is calculated.
--}}
@php
    $small = $small ?? false;
    $caption = $caption ?? null;
    $fiberUnknown = in_array('fiber', $n['missing'] ?? [], true);
    $tiles = [
        ['key' => 'calories', 'value' => $n['calories'] ?? null, 'decimals' => 0, 'unit' => __('common.units.kcal'), 'bar' => 'bg-macro-kcal'],
        ['key' => 'net_carbs', 'value' => $n['net_carbs_g'] ?? null, 'decimals' => 1, 'unit' => __('common.units.g'), 'bar' => 'bg-macro-carbs'],
        ['key' => 'protein', 'value' => $n['protein_g'] ?? null, 'decimals' => 1, 'unit' => __('common.units.g'), 'bar' => 'bg-macro-protein'],
        ['key' => 'fat', 'value' => $n['fat_g'] ?? null, 'decimals' => 1, 'unit' => __('common.units.g'), 'bar' => 'bg-macro-fat'],
    ];
@endphp

<div>
    @if ($caption)
        <p class="mb-1.5 text-sm font-medium text-ink">{{ $caption }}</p>
    @endif
    <dl class="grid grid-cols-4 gap-1.5 text-center">
        @foreach ($tiles as $tile)
            <div class="relative overflow-hidden rounded-control bg-surface-2 px-1 {{ $small ? 'py-1.5' : 'py-2' }}">
                <span class="absolute inset-x-0 top-0 h-0.5 {{ $tile['bar'] }}" aria-hidden="true"></span>
                <dt class="text-xs leading-tight text-ink-muted [overflow-wrap:anywhere]">{{ __('common.macros.'.$tile['key']) }}</dt>
                <dd class="num font-semibold text-ink {{ $small ? 'text-sm' : 'text-base' }}">{{ \App\View\Format::number($tile['value'], $tile['decimals']) }}<span class="ms-0.5 text-xs font-normal text-ink-muted">{{ $tile['unit'] }}</span></dd>
            </div>
        @endforeach
    </dl>
    @if ($fiberUnknown && ! $small)
        <p class="mt-1.5 flex items-start gap-1.5 text-xs text-ink-muted">
            <x-icon name="info" size="xs" class="mt-0.5 shrink-0" />
            <span>{{ __('coach_ui.proposal.fiber_unknown') }}</span>
        </p>
    @endif
</div>
