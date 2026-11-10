@php
    use App\View\Format;

    $kg = __('common.units.kg');
    $g = __('common.units.g');
    $kcal = __('common.units.kcal');
    $targets = $hist['targets'];
    $average = $hist['average'];
    $hasWeights = $trend['entries'] !== [];
@endphp

<div class="space-y-6">
    {{-- ── Weight ─────────────────────────────────────────────────────────────── --}}
    <section class="space-y-4" aria-labelledby="progress-weight-heading">
        <x-page-header level="2" :title="__('progress.weight.title')" id="progress-weight-heading">
            <x-slot:actions>
                <x-button size="sm" icon="plus" wire:click="addWeight">{{ __('progress.weight.add') }}</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <div class="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3">
                <x-stat :label="__('progress.weight.current')" :value="$trend['current_kg']" :decimals="1" :unit="$kg" icon="scale"
                        :delta="$trend['change_kg']" :deltaUnit="$kg" :deltaGood="$trend['goal_kg'] !== null && $trend['start_kg'] !== null ? ($trend['goal_kg'] <= $trend['start_kg'] ? 'down' : 'up') : null"
                        :hint="$trend['change_kg'] !== null ? __('progress.weight.in_period') : null" />
                <x-stat :label="__('progress.weight.start')" :value="$trend['start_kg']" :decimals="1" :unit="$trend['start_kg'] !== null ? $kg : null" />
                <x-stat :label="__('progress.weight.goal')" :value="$trend['goal_kg']" :decimals="1" :unit="$trend['goal_kg'] !== null ? $kg : null" />
                <x-stat :label="__('progress.weight.remaining')" :value="$trend['remaining_kg']" :decimals="1" :unit="$trend['remaining_kg'] !== null ? $kg : null" />
                <x-stat :label="__('progress.weight.weekly_rate')" :unit="$trend['weekly_rate_kg'] !== null ? __('progress.weight.kg_per_week') : null"
                        :value="$trend['weekly_rate_kg'] === null ? null : ($trend['weekly_rate_kg'] > 0 ? '+' : ($trend['weekly_rate_kg'] < 0 ? '−' : '')).Format::number(abs($trend['weekly_rate_kg']), 2)"
                        :hint="$trend['weekly_rate_kg'] === null ? __('progress.weight.rate_needs_two') : null" />
            </div>

            @if ($trend['progress_pct'] !== null)
                <div class="mt-5">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-ink-muted">{{ __('progress.weight.progress') }}</span>
                        <span class="font-semibold text-ink"><span class="num">@num($trend['progress_pct'], 1)</span>{{ __('common.units.percent') }}</span>
                    </div>
                    <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-track" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                         aria-valuenow="{{ Format::number($trend['progress_pct'], 1) }}" aria-label="{{ __('progress.weight.progress') }}">
                        <div class="h-full rounded-full bg-accent" style="width: {{ $trend['progress_pct'] }}%"></div>
                    </div>
                </div>
            @elseif ($trend['goal_kg'] === null)
                <p class="mt-4 text-sm leading-relaxed text-ink-muted">
                    {{ __('progress.weight.no_goal') }}
                    @if (Route::has('profile'))
                        <a href="{{ route('profile') }}" wire:navigate class="font-semibold text-accent-ink underline-offset-4 hover:underline">{{ __('progress.weight.set_goal') }}</a>
                    @endif
                </p>
            @endif
        </x-card>

        @if ($tooFast)
            <x-alert variant="warning" :title="__('progress.weight.fast_title')">
                {{ __('progress.weight.fast_text') }}
            </x-alert>
        @endif

        <x-card :title="__('progress.weight.trend')">
            <x-segmented wire:model.live="range" :label="__('progress.range.label')" :options="$rangeOptions" class="mb-4" />

            @if ($hasWeights)
                <x-chart.line :points="$weightPoints" :target="$trend['goal_kg']" :unit="$kg" :decimals="1" :title="__('progress.weight.trend')" />
                @if (count($weightPoints) === 1)
                    <p class="mt-3 text-sm text-ink-muted">{{ __('progress.weight.one_point') }}</p>
                @endif
            @else
                <x-empty-state compact icon="scale" :title="__('progress.weight.empty_title')" :text="__('progress.weight.empty_text')">
                    <x-button icon="plus" wire:click="addWeight">{{ __('progress.weight.add') }}</x-button>
                </x-empty-state>
            @endif
        </x-card>

        @if ($entries->isNotEmpty())
            <x-card padding="none" :title="null">
                <h3 class="px-4 pb-1 pt-4 font-semibold text-ink">{{ __('progress.weight.entries') }}</h3>
                <ul class="divide-y divide-line">
                    @foreach ($entries as $entry)
                        <li wire:key="weight-{{ $entry->id }}" class="flex min-h-14 items-center gap-2 py-1.5 ps-4 pe-1.5">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink">{{ Format::relativeDay($entry->measured_on) }}</p>
                                <p class="truncate text-sm text-ink-muted">
                                    @if (! $entry->measured_on->isCurrentYear()) @date($entry->measured_on, 'medium') @elseif ($entry->measured_on->isToday() || $entry->measured_on->isYesterday()) @date($entry->measured_on, 'short') @endif
                                    @if ($entry->note) <span>{{ $entry->note }}</span> @endif
                                </p>
                            </div>
                            <p class="shrink-0 font-semibold text-ink"><span class="num">@num($entry->weight_kg, 1)</span> <span class="text-sm font-normal text-ink-muted">{{ $kg }}</span></p>
                            <div class="flex shrink-0 items-center">
                                <x-button variant="ghost" square icon="edit" wire:click="editWeight({{ $entry->id }})"
                                          aria-label="{{ __('progress.weight.edit_entry', ['date' => Format::date($entry->measured_on, 'medium')]) }}" />
                                <x-button variant="danger-ghost" square icon="trash" wire:click="confirmDelete({{ $entry->id }})"
                                          aria-label="{{ __('progress.weight.delete_entry', ['date' => Format::date($entry->measured_on, 'medium')]) }}" />
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if ($hasMore)
                    <div class="border-t border-line p-2">
                        <x-button variant="ghost" block wire:click="showMore">{{ __('progress.weight.show_more') }}</x-button>
                    </div>
                @endif
            </x-card>
        @endif
    </section>

    {{-- ── Nutrition history ──────────────────────────────────────────────────── --}}
    <section class="space-y-4" aria-labelledby="progress-nutrition-heading">
        <x-page-header level="2" :title="__('progress.nutrition.title')" id="progress-nutrition-heading" />

        <x-segmented wire:model.live="history" :label="__('progress.range.label')" :options="$historyOptions" />

        @if ($hist['logged_days'] === 0)
            <x-card>
                <x-empty-state compact icon="flame" :title="__('progress.nutrition.empty_title')" :text="__('progress.nutrition.empty_text')">
                    @if (Route::has('today'))
                        <x-button :href="route('today')" navigate>{{ __('progress.nutrition.go_today') }}</x-button>
                    @endif
                </x-empty-state>
            </x-card>
        @else
            <x-card>
                <div class="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3">
                    <x-stat :label="__('progress.nutrition.streak')" :value="$hist['streak']" :unit="trans_choice('progress.nutrition.days', $hist['streak'])" icon="flame" />
                    <x-stat :label="__('progress.nutrition.logged')" :value="Format::number($hist['logged_days']).' / '.Format::number($hist['total_days'])" :unit="__('progress.nutrition.days_unit')" icon="calendar" />
                    @if ($hist['within_net_carbs'] !== null)
                        <x-stat :label="__('progress.nutrition.adherence')" :value="Format::number($hist['within_net_carbs']).' / '.Format::number($hist['logged_days'])" :unit="__('progress.nutrition.days_unit')" icon="check"
                                :hint="__('progress.nutrition.adherence_hint')" />
                    @endif
                </div>
            </x-card>

            <x-card :title="__('progress.nutrition.net_carbs_chart')" :subtitle="__('progress.nutrition.average_value', ['value' => Format::number($average->netCarbs(), 1), 'unit' => $g])">
                <x-chart.bars :points="$netCarbBars" :target="$targets?->netCarbs" macro="carbs" :unit="$g"
                              :highlight="count($netCarbBars) - 1" :title="__('progress.nutrition.net_carbs_chart')" />
            </x-card>

            <x-card :title="__('progress.nutrition.calories_chart')" :subtitle="__('progress.nutrition.average_value', ['value' => Format::number($average->calories), 'unit' => $kcal])">
                <x-chart.bars :points="$calorieBars" :target="$targets?->calories" macro="kcal" :unit="$kcal"
                              :highlight="count($calorieBars) - 1" :title="__('progress.nutrition.calories_chart')" />
            </x-card>

            <x-card :title="__('progress.nutrition.averages')" :subtitle="__('progress.nutrition.averages_hint')">
                <div class="space-y-4">
                    <x-macro-bar :label="__('common.macros.net_carbs')" macro="carbs" :value="$average->netCarbs()" :target="$targets?->netCarbs" :unit="$g" :decimals="1"
                                 :remaining="false" />
                    <x-macro-bar :label="__('common.macros.calories')" macro="kcal" :value="$average->calories" :target="$targets?->calories" :unit="$kcal" :remaining="false" />
                    <x-macro-bar :label="__('common.macros.protein')" macro="protein" :limit="false" :value="$average->protein" :target="$targets?->protein" :unit="$g" :decimals="1" :remaining="false" />
                    <x-macro-bar :label="__('common.macros.fat')" macro="fat" :value="$average->fat" :target="$targets?->fat" :unit="$g" :decimals="1" :remaining="false" />
                </div>
            </x-card>
        @endif
    </section>

    {{-- ── Weigh-in sheet ─────────────────────────────────────────────────────── --}}
    <x-bottom-sheet name="weight-entry" :title="__('progress.weight.sheet_title')" :description="__('progress.weight.replace_hint')">
        <form wire:submit="saveWeight" novalidate id="weight-entry-form" class="space-y-4">
            <x-input wire:model="weight_kg" type="number" inputmode="decimal" step="0.1" :label="__('macros.fields.weight_kg')" :suffix="$kg" />
            <x-input wire:model="measured_on" type="date" max="{{ $today }}" :label="__('progress.weight.date')" />
            <x-input wire:model="note" :label="__('progress.weight.note')" optional maxlength="255" />
        </form>

        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'weight-entry')">{{ __('common.cancel') }}</x-button>
            <x-button type="submit" form="weight-entry-form" loading="saveWeight">{{ __('common.save') }}</x-button>
        </x-slot:footer>
    </x-bottom-sheet>

    <x-modal name="delete-weight" :title="__('progress.weight.delete_title')" size="sm">
        <p class="text-[0.9375rem] leading-relaxed text-ink-muted">
            @if ($deleting)
                {{ __('progress.weight.delete_text', ['date' => Format::date($deleting->measured_on, 'medium'), 'kg' => Format::number($deleting->weight_kg, 1)]) }}
            @else
                {{ __('progress.weight.delete_text_generic') }}
            @endif
        </p>

        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'delete-weight')">{{ __('common.cancel') }}</x-button>
            <x-button variant="danger" wire:click="deleteWeight">{{ __('common.delete') }}</x-button>
        </x-slot:footer>
    </x-modal>
</div>
