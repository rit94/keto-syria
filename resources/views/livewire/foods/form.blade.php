@php
    $g = __('common.units.g');
    $perServing = $basisMode === 'serving';
@endphp

<div class="mx-auto max-w-2xl space-y-4">
    @if ($deleteError)
        <x-alert variant="danger">{{ $deleteError }}</x-alert>
    @endif

    <form wire:submit="save" class="space-y-4" novalidate>
        <x-card :title="__('foods.form.names')">
            <div class="space-y-4">
                <x-input wire:model="name_ar" :label="__('foods.form.name_ar')" lang="ar" dir="rtl" autocomplete="off" />
                <x-input wire:model="name_en" :label="__('foods.form.name_en')" lang="en" dir="ltr" autocomplete="off" optional />
                <x-textarea wire:model="aliasesText" :label="__('foods.form.aliases')" :hint="__('foods.form.aliases_hint')" rows="2" optional />
            </div>
        </x-card>

        <x-card :title="__('foods.form.kind')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select wire:model="category" :label="__('foods.form.category')" :options="$categoryOptions" :selected="$category" />
                <x-select wire:model.live="state" :label="__('foods.form.state')" :options="$stateOptions" :selected="$state" />
                <x-select wire:model="preparation" :label="__('foods.form.preparation')" :options="$preparationOptions" :selected="$preparation" optional />
                @if ($state === 'raw')
                    <x-input wire:model="cooked_yield" type="number" step="any" inputmode="decimal" :label="__('foods.form.cooked_yield')" :hint="__('foods.form.cooked_yield_hint')" optional />
                @endif
            </div>
        </x-card>

        <x-card :title="__('foods.form.values')" :subtitle="$perServing ? __('foods.form.values_serving') : __('foods.form.values_100')">
            <div class="space-y-4">
                <x-segmented wire:model.live="basisMode" :label="__('foods.form.amount_basis')" :options="['per100' => __('foods.form.per_100'), 'serving' => __('foods.form.per_serving')]" />

                @if ($perServing)
                    <x-input wire:model.live.debounce.400ms="per_grams" type="number" step="any" inputmode="decimal" :label="__('foods.form.serving_size')" :suffix="$g" :hint="__('foods.form.serving_hint')" />
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <x-input wire:model.live.debounce.400ms="calories" type="number" step="any" inputmode="decimal" :label="__('common.macros.calories')" :suffix="__('common.units.kcal')" />
                    <x-input wire:model.live.debounce.400ms="protein_g" type="number" step="any" inputmode="decimal" :label="__('common.macros.protein')" :suffix="$g" />
                    <x-input wire:model.live.debounce.400ms="carbs_g" type="number" step="any" inputmode="decimal" :label="__('foods.form.carbs')" :suffix="$g" />
                    <x-input wire:model.live.debounce.400ms="fiber_g" type="number" step="any" inputmode="decimal" :label="__('common.macros.fiber')" :suffix="$g" optional />
                    <x-input wire:model.live.debounce.400ms="fat_g" type="number" step="any" inputmode="decimal" :label="__('common.macros.fat')" :suffix="$g" />
                    <x-input wire:model.live.debounce.400ms="saturated_fat_g" type="number" step="any" inputmode="decimal" :label="__('foods.form.saturated_fat')" :suffix="$g" optional />
                </div>
                <p class="text-sm text-ink-muted">{{ __('foods.form.blank_unknown') }}</p>

                <div>
                    <x-segmented wire:model.live="label_basis" :label="__('foods.form.carb_basis')" showLabel :options="$basisOptions" />
                    <p class="mt-2 text-sm text-ink-muted">
                        {{ $label_basis === 'net' ? __('foods.form.basis_net') : __('foods.form.basis_total') }}
                        {{ __('foods.form.basis_explain') }}
                    </p>
                </div>

                @foreach ($warnings as $warning)
                    <x-alert variant="warning" wire:key="warning-{{ $warning['code'] }}">{{ $warning['message'] }}</x-alert>
                @endforeach

                @if ($formError)
                    <x-alert variant="danger">{{ $formError }}</x-alert>
                @endif

                <x-checkbox wire:model="is_estimate" :label="__('foods.form.is_estimate')" />
                <p class="-mt-2 ps-8 text-sm text-ink-muted">{{ __('foods.form.is_estimate_hint') }}</p>
            </div>
        </x-card>

        <x-card :title="__('foods.form.units')" :subtitle="__('foods.form.units_hint')">
            <div class="space-y-3">
                @foreach ($units as $i => $unit)
                    <div wire:key="unit-{{ $unit['unit'] }}" class="rounded-control bg-surface-2 p-3">
                        <div class="grid grid-cols-[minmax(0,1fr)_7rem] gap-3">
                            <x-input wire:model="units.{{ $i }}.label" :label="__('foods.form.unit_name')" :error="$errors->first('units.'.$i.'.label_ar') ?: $errors->first('units.'.$i.'.label_en') ?: $errors->first('units.'.$i.'.unit') ?: null" />
                            <x-input wire:model="units.{{ $i }}.grams" type="number" step="any" inputmode="decimal" :label="__('foods.form.unit_grams')" :suffix="$g" :error="$errors->first('units.'.$i.'.grams') ?: null" />
                        </div>
                        <div class="mt-1 flex items-center justify-between gap-2">
                            <label class="flex min-h-touch cursor-pointer items-center gap-2 text-sm text-ink">
                                <input type="radio" name="default-unit" class="size-5 accent-accent" @checked($unit['is_default']) wire:click="makeDefault({{ $i }})">
                                {{ __('foods.form.unit_default') }}
                            </label>
                            <x-button variant="danger-ghost" size="sm" icon="trash" wire:click="removeUnit({{ $i }})" aria-label="{{ __('foods.form.remove_unit') }}">{{ __('common.delete') }}</x-button>
                        </div>
                    </div>
                @endforeach

                <x-button variant="secondary" icon="plus" wire:click="addUnit">{{ __('foods.form.add_unit') }}</x-button>
            </div>
        </x-card>

        <x-card :title="__('foods.form.notes')">
            <div class="space-y-4">
                <x-textarea wire:model="notes_ar" :label="__('foods.form.notes_ar')" rows="2" optional />
                <x-textarea wire:model="notes_en" :label="__('foods.form.notes_en')" rows="2" optional />
            </div>
        </x-card>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            @if ($editing)
                <x-button variant="danger-ghost" icon="trash" x-on:click="$dispatch('open-modal', 'delete-food')" class="sm:me-auto">{{ __('common.delete') }}</x-button>
            @endif
            <x-button variant="secondary" :href="$editing ? route('foods.show', $foodId) : route('foods.index')" navigate>{{ __('common.cancel') }}</x-button>
            <x-button type="submit" loading="save" icon="check">{{ __('common.save') }}</x-button>
        </div>
    </form>

    @if ($editing)
        <x-modal name="delete-food" size="sm" :title="__('foods.delete_title')">
            <p class="text-ink-muted">{{ __('foods.delete_text', ['name' => $name_ar ?: $name_en]) }}</p>
            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'delete-food')">{{ __('common.cancel') }}</x-button>
                <x-button variant="danger" wire:click="delete">{{ __('common.delete') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
