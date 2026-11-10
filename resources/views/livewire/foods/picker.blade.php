<div class="space-y-4">
    @if ($food)
        {{-- Step 2: how much --}}
        <div class="flex items-start gap-2">
            <button
                type="button"
                wire:click="back"
                class="-ms-2 flex size-touch shrink-0 items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink"
                aria-label="{{ __('foods.picker.change_food') }}"
            >
                <x-icon name="chevron-start" />
            </button>
            <div class="min-w-0 flex-1 pt-1.5">
                <p class="text-base font-semibold leading-snug text-ink">{{ $food->displayName() }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    @if ($food->state && $food->state !== \App\Enums\FoodState::NotApplicable)
                        <x-badge>{{ $food->state->label() }}</x-badge>
                    @endif
                    @if ($food->preparation)
                        <x-badge>{{ $food->preparation->label() }}</x-badge>
                    @endif
                    @if ($food->is_estimate)
                        <x-badge estimate />
                    @endif
                    @if ($food->confidence)
                        <x-badge :confidence="$food->confidence" />
                    @endif
                </div>
            </div>
        </div>

        <form wire:submit="confirm" class="space-y-4" novalidate>
            <div class="grid grid-cols-2 gap-3">
                <div wire:key="picker-quantity-{{ $context }}-{{ $food->id }}-{{ $unit }}">
                    <x-number-stepper
                        wire:model.live.debounce.300ms="quantity"
                        id="picker-{{ $context }}-quantity"
                        :label="__('foods.picker.quantity')"
                        :step="$unit === 'g' ? 10 : 0.5"
                        :error="$previewError && $previewError['field'] === 'quantity' ? $previewError['message'] : null"
                    />
                </div>
                <x-select
                    wire:model.live="unit"
                    id="picker-{{ $context }}-unit"
                    :label="__('foods.picker.unit')"
                    :options="$unitOptions"
                    :selected="$unit"
                    :error="$previewError && $previewError['field'] === 'unit' ? $previewError['message'] : null"
                />
            </div>

            @if ($canWeighCooked)
                <div>
                    <x-checkbox wire:model.live="weighedCooked" id="picker-{{ $context }}-cooked" :label="__('foods.picker.weighed_cooked')" />
                    <p class="ps-8 text-sm text-ink-muted">{{ __('foods.picker.weighed_cooked_hint') }}</p>
                </div>
            @endif

            @if ($previewError && $previewError['field'] === 'weighedCooked')
                <x-alert variant="danger">{{ $previewError['message'] }}</x-alert>
            @endif

            <div wire:loading.class="opacity-60" wire:target="quantity, unit, weighedCooked" class="transition-opacity duration-150">
                <p class="mb-1.5 text-sm font-medium text-ink">{{ __('foods.picker.preview') }}</p>
                @include('livewire.foods.partials.nutrient-tiles', ['nutrients' => $nutrients])
            </div>

            <x-button type="submit" loading="confirm" size="lg" block icon="check">
                {{ $meal && \App\Enums\Meal::tryFrom($meal) ? __('foods.picker.add_to', ['meal' => \App\Enums\Meal::from($meal)->label()]) : __('foods.picker.confirm') }}
            </x-button>
        </form>
    @else
        {{-- Step 1: find a food --}}
        <div>
            <x-input
                wire:model.live.debounce.300ms="query"
                id="picker-{{ $context }}-query"
                type="search"
                icon="search"
                autocomplete="off"
                enterkeyhint="search"
                :placeholder="__('foods.search_placeholder')"
                aria-label="{{ __('foods.search_label') }}"
            />
        </div>

        <div class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]" role="group" aria-label="{{ __('foods.category_filter') }}">
            @php
                $chip = 'inline-flex min-h-9 shrink-0 items-center whitespace-nowrap rounded-full border px-3.5 text-sm font-medium transition-colors duration-150';
                $chipOn = 'border-accent bg-accent-soft text-accent-ink';
                $chipOff = 'border-line-strong bg-surface text-ink-muted hover:text-ink';
            @endphp
            <button type="button" wire:click="$set('category', '')" aria-pressed="{{ $category === '' ? 'true' : 'false' }}" class="{{ $chip }} {{ $category === '' ? $chipOn : $chipOff }}">
                {{ __('foods.all_categories') }}
            </button>
            @foreach ($categories as $case)
                <button type="button" wire:click="$set('category', '{{ $case->value }}')" aria-pressed="{{ $category === $case->value ? 'true' : 'false' }}" class="{{ $chip }} {{ $category === $case->value ? $chipOn : $chipOff }}">
                    {{ $case->label() }}
                </button>
            @endforeach
        </div>

        <div wire:loading.class="opacity-60" wire:target="query, category" class="min-h-48 space-y-4 transition-opacity duration-150">
            @foreach ([[__('foods.picker.recent'), $recent], [$searching ? __('foods.picker.results') : __('foods.picker.common'), $results]] as [$heading, $foods])
                @if ($foods->isNotEmpty())
                    <section>
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-ink-subtle">
                            {{ $heading }}
                            <span class="font-normal normal-case">· {{ __('foods.per_100g') }}</span>
                        </h3>
                        <ul class="divide-y divide-line">
                            @foreach ($foods as $result)
                                <li wire:key="picker-{{ $context }}-food-{{ $result->id }}">
                                    <button
                                        type="button"
                                        wire:click="select({{ $result->id }})"
                                        class="-mx-2 flex w-[calc(100%+1rem)] items-center gap-2 rounded-control px-2 py-3 text-start transition-colors duration-150 hover:bg-surface-2 focus-visible:-outline-offset-2"
                                    >
                                        @include('livewire.foods.partials.food-summary', ['food' => $result])
                                        <x-icon name="{{ $withQuantity ? 'chevron-end' : 'plus' }}" size="sm" class="shrink-0 text-ink-subtle" />
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach

            @if ($results->isEmpty() && $recent->isEmpty())
                <x-empty-state icon="search" compact :title="__('foods.picker.nothing_title')" :text="__('foods.picker.nothing_text')">
                    <x-button icon="plus" :href="route('foods.create', array_filter(['name' => trim($query)]))">{{ __('foods.create_custom') }}</x-button>
                </x-empty-state>
            @elseif ($searching)
                <p class="text-center text-sm text-ink-muted">
                    {{ __('foods.picker.not_found_hint') }}
                    <a href="{{ route('foods.create', ['name' => trim($query)]) }}" class="font-semibold text-accent-ink underline-offset-4 hover:underline">{{ __('foods.create_custom') }}</a>
                </p>
            @endif

            @error('query')
                <x-alert variant="danger">{{ $message }}</x-alert>
            @enderror
        </div>
    @endif
</div>
