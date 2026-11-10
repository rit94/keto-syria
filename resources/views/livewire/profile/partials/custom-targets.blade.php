{{--
    Manual target inputs. Validation (safety floor, macro/calorie mismatch) is ProfileService's;
    its messages appear under the inputs. The macro-calorie sum is engine output passed in.
    Variables: $minCalories (int), $macroCalories (?float)
--}}
<div class="space-y-4">
    <x-input
        wire:model.live.debounce.400ms="calories"
        type="number" inputmode="decimal" step="10" min="{{ $minCalories }}"
        :label="__('common.macros.calories')"
        :suffix="__('common.units.kcal')"
        :hint="__('profile.custom.floor_hint', ['min' => \App\View\Format::number($minCalories)])"
    />

    <div class="grid grid-cols-2 gap-3">
        <x-input wire:model.live.debounce.400ms="net_carbs_g" type="number" inputmode="decimal" step="1" min="0"
                 :label="__('common.macros.net_carbs')" :suffix="__('common.units.g')" />
        <x-input wire:model.live.debounce.400ms="carbs_g" type="number" inputmode="decimal" step="1" min="0"
                 :label="__('profile.targets.total_carbs')" :suffix="__('common.units.g')" />
        <x-input wire:model.live.debounce.400ms="protein_g" type="number" inputmode="decimal" step="1" min="0"
                 :label="__('common.macros.protein')" :suffix="__('common.units.g')" />
        <x-input wire:model.live.debounce.400ms="fat_g" type="number" inputmode="decimal" step="1" min="0"
                 :label="__('common.macros.fat')" :suffix="__('common.units.g')" />
    </div>

    <div class="rounded-control bg-surface-2 p-3 text-sm" aria-live="polite">
        <div class="flex items-center justify-between gap-3">
            <span class="text-ink-muted">{{ __('profile.custom.macro_sum') }}</span>
            <span class="font-semibold text-ink"><span class="num">@num($macroCalories)</span> {{ __('common.units.kcal') }}</span>
        </div>
        <div class="mt-1 flex items-center justify-between gap-3">
            <span class="text-ink-muted">{{ __('profile.custom.calorie_target') }}</span>
            <span class="font-semibold text-ink"><span class="num">@num(is_numeric($calories) ? $calories : null)</span> {{ __('common.units.kcal') }}</span>
        </div>
        <p class="mt-2 text-xs leading-relaxed text-ink-subtle">{{ __('profile.custom.sum_hint') }}</p>
    </div>
</div>
