@php
    $g = __('common.units.g');
    $per = $food->per100g();
    $n = $per->rounded();
    $fiberUnknown = $per->isMissing('fiber');
    $rows = [
        [__('common.macros.calories'), $n['calories'], 0, __('common.units.kcal'), false],
        [__('common.macros.protein'), $n['protein_g'], 1, $g, false],
        [__('foods.facts.carbs'), $n['carbs_g'], 1, $g, false],
        [__('common.macros.fiber'), $n['fiber_g'], 1, $g, true],
        [__('common.macros.net_carbs'), $n['net_carbs_g'], 1, $g, false],
        [__('common.macros.fat'), $n['fat_g'], 1, $g, false],
        [__('foods.facts.saturated_fat'), $n['saturated_fat_g'], 1, $g, true],
    ];
    $notes = app()->getLocale() === 'ar' ? ($food->notes_ar ?: $food->notes_en) : ($food->notes_en ?: $food->notes_ar);
    $anyUnknown = $fiberUnknown || $per->isMissing('saturated_fat');
@endphp

@if ($canEdit)
    <x-slot:actions>
        <x-button variant="ghost" square icon="edit" :href="route('foods.edit', $food)" navigate aria-label="{{ __('common.edit') }}" />
    </x-slot:actions>
@endif

<div class="mx-auto max-w-2xl space-y-4">
    @if ($deleteError)
        <x-alert variant="danger">{{ $deleteError }}</x-alert>
    @endif

    <div class="flex flex-wrap items-center gap-1.5">
        <x-badge variant="accent">{{ $food->category?->label() }}</x-badge>
        @if ($food->state && $food->state !== \App\Enums\FoodState::NotApplicable)<x-badge>{{ $food->state->label() }}</x-badge>@endif
        @if ($food->preparation)<x-badge>{{ $food->preparation->label() }}</x-badge>@endif
        @if ($food->isCustom())<x-badge variant="accent">{{ __('foods.mine_badge') }}</x-badge>@endif
        @if ($food->is_estimate)<x-badge estimate />@endif
        @if ($food->confidence)<x-badge :confidence="$food->confidence" />@endif
    </div>

    <x-button icon="plus" size="lg" block wire:click="openLog">{{ __('foods.log_this') }}</x-button>

    @if ($food->is_estimate)
        <x-alert variant="warning" :title="__('foods.estimate_title')">
            {{ __('foods.estimate_text') }}
            @if ($notes)<span class="mt-1 block">{{ $notes }}</span>@endif
        </x-alert>
    @elseif ($notes)
        <x-alert variant="info">{{ $notes }}</x-alert>
    @endif

    <x-card :title="__('foods.per_100g')" padding="none">
        <dl class="divide-y divide-line border-t border-line">
            @foreach ($rows as [$label, $value, $decimals, $unit, $mayBeUnknown])
                <div class="flex min-h-12 items-center justify-between gap-3 px-4 py-2">
                    <dt class="text-[0.9375rem] text-ink {{ $label === __('common.macros.net_carbs') ? 'font-semibold' : '' }}">{{ $label }}</dt>
                    <dd class="text-end">
                        @if ($value === null)
                            <span class="text-ink-muted" title="{{ __('foods.unknown_explain') }}"><span class="num">—</span> <span class="text-sm">{{ __('foods.unknown_short') }}</span></span>
                        @else
                            <span class="num font-semibold text-ink">@num($value, $decimals)</span> <span class="text-sm text-ink-muted">{{ $unit }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
            @foreach ($food->extra_nutrients ?? [] as $key => $extra)
                <div class="flex min-h-12 items-center justify-between gap-3 px-4 py-2">
                    <dt class="text-[0.9375rem] text-ink">{{ \Illuminate\Support\Facades\Lang::has('foods.extras.'.$key) ? __('foods.extras.'.$key) : $key }}</dt>
                    <dd><span class="num font-semibold text-ink">@num($extra, 1)</span> <span class="text-sm text-ink-muted">{{ $g }}</span></dd>
                </div>
            @endforeach
        </dl>
        @if ($anyUnknown)
            <p class="border-t border-line px-4 py-3 text-sm text-ink-muted">
                <span class="num">—</span> {{ __('foods.unknown_explain') }}
                @if ($fiberUnknown) {{ __('foods.unknown_fiber_net') }} @endif
            </p>
        @endif
    </x-card>

    <x-card :title="__('foods.source')">
        <dl class="space-y-1.5 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('foods.source') }}</dt><dd class="text-end text-ink">{{ $food->source ?: '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('common.confidence') }}</dt><dd class="text-end text-ink">{{ $food->confidence?->label() ?? '—' }}</dd></div>
            @if ($food->cooked_yield)
                <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('foods.form.cooked_yield') }}</dt><dd class="num text-end text-ink">@num($food->cooked_yield, 2)</dd></div>
            @endif
        </dl>
    </x-card>

    @if ($units->isNotEmpty())
        <x-card :title="__('foods.units_title')" padding="none">
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($units as $unit)
                    <li class="flex min-h-12 items-center justify-between gap-3 px-4 py-2 text-[0.9375rem]">
                        <span class="text-ink">{{ $unit['label'] }}</span>
                        <span class="text-ink-muted"><span class="num">@num($unit['grams'], 1)</span> {{ $g }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($variants->isNotEmpty())
        <x-card :title="__('foods.variants')" padding="none">
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($variants as $variant)
                    <li><x-list-row :href="route('foods.show', $variant)" :title="$variant->displayName()" :subtitle="collect([$variant->state?->label(), $variant->preparation?->label()])->filter()->implode(' · ')" /></li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($canEdit)
        <div class="flex flex-wrap gap-2">
            <x-button variant="secondary" icon="edit" :href="route('foods.edit', $food)" navigate>{{ __('common.edit') }}</x-button>
            <x-button variant="danger-ghost" icon="trash" x-on:click="$dispatch('open-modal', 'delete-food')">{{ __('common.delete') }}</x-button>
        </div>

        <x-modal name="delete-food" size="sm" :title="__('foods.delete_title')">
            <p class="text-ink-muted">{{ __('foods.delete_text', ['name' => $food->displayName()]) }}</p>
            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'delete-food')">{{ __('common.cancel') }}</x-button>
                <x-button variant="danger" wire:click="delete">{{ __('common.delete') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

    <x-bottom-sheet name="log-food" :title="$food->displayName()" size="lg">
        <div class="space-y-4">
            <x-segmented wire:model.live="meal" :label="__('today.edit.meal')" :options="$mealOptions" />
            <livewire:foods.picker :context="\App\Livewire\Foods\Show::PICKER_CONTEXT" :with-quantity="true" :food-id="$food->id" wire:key="show-picker" />
        </div>
    </x-bottom-sheet>
</div>
