@php
    use App\View\Format;

    $g = __('common.units.g');
    $deleteModal = \App\Livewire\Plans\Show::DELETE_MODAL;
@endphp

<x-slot:actions>
    <x-button variant="ghost" square icon="edit" :href="route('plans.edit', $plan)" navigate aria-label="{{ __('common.edit') }}" />
</x-slot:actions>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-ink-muted">
            @date($plan->starts_on, 'medium') – @date($ends, 'medium')
            · <span class="num">@num($plan->days)</span> {{ __('plans.days_unit') }}
            @if ($plan->origin === 'ai')<x-badge variant="accent" icon="sparkles" class="ms-1">{{ __('plans.from_coach') }}</x-badge>@endif
        </p>
        <x-button variant="danger-ghost" size="sm" icon="trash" x-on:click="$dispatch('open-modal', '{{ $deleteModal }}')">{{ __('common.delete') }}</x-button>
    </div>

    <x-tabs wire:model.live="tab" :label="$plan->name" :tabs="['plan' => __('plans.show.tab_plan'), 'shopping' => __('plans.show.tab_shopping')]" />

    @if ($tab === 'shopping')
        {{-- Shopping list --}}
        @if ($shoppingList->isEmpty())
            <x-empty-state icon="list" compact :title="__('plans.show.shopping_empty')" :text="__('plans.show.shopping_empty_text')" />
        @else
            <p class="text-sm text-ink-muted">{{ __('plans.show.shopping_hint') }}</p>
            @foreach ($shoppingList as $group)
                <x-card :title="\App\Enums\FoodCategory::from($group['category'])->label()" padding="sm" wire:key="shop-{{ $group['category'] }}">
                    <ul class="divide-y divide-line">
                        @foreach ($group['items'] as $line)
                            <li class="flex items-baseline justify-between gap-3 py-2.5">
                                <span class="min-w-0 text-ink">{{ $line['food']->displayName() }}</span>
                                <span class="shrink-0 text-ink-muted">
                                    @if ($line['grams'] >= 1000)
                                        <span class="num font-semibold text-ink">{{ Format::number($line['grams'] / 1000, 2) }}</span> {{ __('common.units.kg') }}
                                    @else
                                        <span class="num font-semibold text-ink">{{ Format::number($line['grams'], 0) }}</span> {{ $g }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endforeach
        @endif
    @else
        {{-- Day selector --}}
        <div class="-mx-gutter flex gap-2 overflow-x-auto px-gutter pb-1 [scrollbar-width:none] lg:mx-0 lg:grid lg:grid-cols-7 lg:overflow-visible lg:px-0" role="tablist" aria-label="{{ __('plans.days') }}">
            @foreach ($days as $d)
                @php $selected = $d['index'] === $day['index']; @endphp
                <button type="button" role="tab" aria-selected="{{ $selected ? 'true' : 'false' }}" wire:click="selectDay({{ $d['index'] }})" wire:key="day-tab-{{ $d['index'] }}"
                        @class([
                            'flex min-h-touch w-20 shrink-0 flex-col items-center justify-center gap-0.5 rounded-control border px-2 py-2 text-center transition-colors duration-150 lg:w-auto',
                            'border-accent bg-accent-soft text-accent-ink' => $selected,
                            'border-line-strong bg-surface text-ink-muted hover:text-ink' => ! $selected,
                        ])>
                    <span class="text-xs">{{ $d['date']->isSameDay($today) ? __('common.today') : Format::date($d['date'], 'day') }}</span>
                    <span class="num text-base font-semibold">@num($d['date']->day)</span>
                    @if ($d['count'] > 0)
                        <span class="num text-xs {{ $d['over'] ? 'font-semibold text-danger-ink' : '' }}">
                            @if ($d['over'])<span class="sr-only">{{ __('plans.over_carbs') }}</span>@endif{{ Format::number($d['totals']->netCarbs(), 1) }} {{ $g }}
                        </span>
                    @else
                        <span class="text-xs">—</span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
            <div class="min-w-0 space-y-4">
                <h2 class="text-base font-semibold text-ink">@date($day['date'], 'weekday')</h2>

                @foreach ($meals as $slot)
                    @php
                        $pending = $slot['items']->filter(fn ($row) => ! $row['logged'] && $row['available'])->count();
                    @endphp
                    <x-card :title="$slot['meal']->label()" wire:key="meal-{{ $slot['meal']->value }}">
                        @if ($pending > 0)
                            <x-slot:actions>
                                <x-button variant="ghost" size="sm" icon="check" wire:click="logMeal('{{ $slot['meal']->value }}')">{{ __('plans.show.log_meal') }}</x-button>
                            </x-slot:actions>
                        @endif

                        @if ($slot['items']->isEmpty())
                            <p class="text-sm text-ink-subtle">{{ __('plans.meal_empty') }}</p>
                        @else
                            <ul class="-my-3 divide-y divide-line">
                                @foreach ($slot['items'] as $row)
                                    <li class="flex items-center justify-between gap-3 py-3" wire:key="item-{{ $row['item']->id }}">
                                        <div class="min-w-0">
                                            <p class="font-medium leading-snug text-ink">{{ $row['name'] }}</p>
                                            <p class="mt-0.5 text-sm text-ink-muted">
                                                <span class="num">{{ Format::number($row['quantity'], 2) }}</span> {{ $row['unit'] }}
                                                @if ($row['nutrients'])
                                                    · <span class="num">{{ Format::number($row['nutrients']->calories) }}</span> {{ __('common.units.kcal') }}
                                                    · {{ __('common.macros.net_carbs') }} <span class="num">{{ Format::number($row['nutrients']->netCarbs(), 1) }}</span> {{ $g }}
                                                @endif
                                            </p>
                                            @if ($row['item']->note)<p class="text-sm text-ink-subtle">{{ $row['item']->note }}</p>@endif
                                        </div>
                                        @if ($row['logged'])
                                            <x-badge variant="success" icon="check">{{ __('plans.show.logged_badge') }}</x-badge>
                                        @elseif ($row['available'])
                                            <x-button variant="secondary" size="sm" wire:click="logItem({{ $row['item']->id }})">{{ __('plans.show.log_it') }}</x-button>
                                        @else
                                            <x-badge variant="warning">{{ __('plans.deleted_item') }}</x-badge>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>
                @endforeach
            </div>

            <div class="min-w-0 lg:sticky lg:top-[calc(var(--spacing-topbar)+1.5rem)]">
                <x-card :title="__('plans.day_totals')">
                    @if ($day['count'] === 0)
                        <p class="text-sm text-ink-muted">{{ __('plans.day_empty') }}</p>
                    @else
                        @include('livewire.plans.partials.day-totals', ['totals' => $day['totals'], 'targets' => $targets])
                        @if ($day['over'])
                            <x-alert variant="danger" class="mt-3">{{ __('plans.over_carbs_text') }}</x-alert>
                        @endif
                    @endif
                </x-card>
            </div>
        </div>
    @endif

    <x-modal :name="$deleteModal" :title="__('plans.delete_title')" size="sm">
        <p class="text-ink-muted">{{ __('plans.delete_text', ['name' => $plan->name]) }}</p>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', '{{ $deleteModal }}')">{{ __('common.cancel') }}</x-button>
            <x-button variant="danger" wire:click="delete">{{ __('common.delete') }}</x-button>
        </x-slot:footer>
    </x-modal>
</div>
