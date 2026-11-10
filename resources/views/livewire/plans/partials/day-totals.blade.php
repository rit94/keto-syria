{{--
    A day's planned totals against the user's daily targets. Engine output only.

    $totals   App\Domain\Nutrition\Nutrients   (from MealPlanService::dayNutrition / ItemNutrition)
    $targets  App\Domain\Nutrition\MacroTargets|null
--}}
@php
    $g = __('common.units.g');
    $lower = ! $totals->isComplete();
@endphp

<div class="space-y-3">
    <x-macro-bar :label="__('common.macros.net_carbs')" macro="carbs" :value="$totals->netCarbs()" :target="$targets?->netCarbs" :unit="$g" :decimals="1" :lowerBound="$lower" />
    <x-macro-bar :label="__('common.macros.calories')" macro="kcal" :value="$totals->calories" :target="$targets?->calories" :unit="__('common.units.kcal')" />
    <div class="grid grid-cols-2 gap-3">
        <x-macro-bar :label="__('common.macros.protein')" macro="protein" :limit="false" :value="$totals->protein" :target="$targets?->protein" :unit="$g" :decimals="1" />
        <x-macro-bar :label="__('common.macros.fat')" macro="fat" :limit="false" :value="$totals->fat" :target="$targets?->fat" :unit="$g" :decimals="1" />
    </div>
</div>
