{{--
    Daily targets display. Numbers come from MacroResult::roundedTargets() or Profile::targets().
    Variables: $targets (App\Domain\Nutrition\MacroTargets)
--}}
<div>
    <div class="flex items-baseline gap-2">
        <span class="num text-4xl font-semibold leading-none text-ink">@num($targets->calories)</span>
        <span class="text-ink-muted">{{ __('profile.targets.kcal_per_day') }}</span>
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-2.5">
        @foreach ([
            ['net_carbs', $targets->netCarbs, 'bg-macro-carbs', __('profile.targets.net_carbs_hint')],
            ['protein', $targets->protein, 'bg-macro-protein', __('profile.targets.protein_hint')],
            ['fat', $targets->fat, 'bg-macro-fat', __('profile.targets.fat_hint')],
            ['carbs', $targets->carbs, 'bg-macro-fiber', __('profile.targets.carbs_hint')],
        ] as [$key, $value, $dot, $hint])
            <div class="rounded-control bg-surface-2 p-3">
                <dt class="flex items-center gap-1.5 text-sm text-ink-muted">
                    <span class="size-2 shrink-0 rounded-full {{ $dot }}" aria-hidden="true"></span>
                    <span>{{ $key === 'carbs' ? __('profile.targets.total_carbs') : __('common.macros.'.$key) }}</span>
                </dt>
                <dd class="mt-1">
                    <span class="num text-xl font-semibold text-ink">@num($value, 1)</span>
                    <span class="text-sm text-ink-muted">{{ __('common.units.g') }}</span>
                    <span class="mt-0.5 block text-xs text-ink-subtle">{{ $hint }}</span>
                </dd>
            </div>
        @endforeach
    </dl>
</div>
