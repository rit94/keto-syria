{{-- One style-guide panel. $p = panel key (ar|en) used to keep ids and names unique. --}}
@php
    use App\View\Format;

    $h = 'mb-3 mt-10 border-b border-line pb-2 text-sm font-semibold uppercase tracking-wide text-ink-muted first:mt-0';
    $g = __('common.units.g');

    $swatches = [
        'canvas' => 'bg-canvas', 'surface' => 'bg-surface', 'surface-2' => 'bg-surface-2', 'surface-3' => 'bg-surface-3',
        'ink' => 'bg-ink', 'ink-muted' => 'bg-ink-muted', 'ink-subtle' => 'bg-ink-subtle', 'line-strong' => 'bg-line-strong',
        'accent' => 'bg-accent', 'accent-soft' => 'bg-accent-soft', 'danger' => 'bg-danger', 'danger-soft' => 'bg-danger-soft',
        'warning' => 'bg-warning', 'success' => 'bg-success', 'info' => 'bg-info', 'track' => 'bg-track',
        'macro-kcal' => 'bg-macro-kcal', 'macro-carbs' => 'bg-macro-carbs', 'macro-protein' => 'bg-macro-protein',
        'macro-fat' => 'bg-macro-fat', 'macro-fiber' => 'bg-macro-fiber',
    ];

    $icons = [
        'plus', 'minus', 'search', 'mic', 'camera', 'chat', 'send', 'chevron-start', 'chevron-end', 'chevron-down',
        'chevron-up', 'arrow-start', 'arrow-end', 'edit', 'trash', 'check', 'x', 'flame', 'calendar', 'chart', 'user',
        'book', 'list', 'sun', 'moon', 'globe', 'alert', 'info', 'logout', 'menu', 'more', 'clock', 'scale', 'sparkles', 'spinner',
    ];

    $weights = [84.6, 84.1, 83.9, 83.2, null, 82.8, 82.1, 81.7];
    $weightLabels = collect(range(1, 8))->map(fn ($n) => __('ui.week', ['n' => $n]))->all();
    $carbs = array_combine(__('ui.days'), [18, 22, 14, 27, 19, 16, 12]);
@endphp

{{-- Colours --}}
<h2 class="{{ $h }}">{{ __('ui.sections.colors') }}</h2>
<div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
    @foreach ($swatches as $name => $class)
        <div class="min-w-0">
            <div class="h-10 rounded-control border border-line {{ $class }}"></div>
            <p class="mt-1 truncate text-xs text-ink-muted" dir="ltr">{{ $name }}</p>
        </div>
    @endforeach
</div>

{{-- Type --}}
<h2 class="{{ $h }}">{{ __('ui.sections.type') }}</h2>
<div class="space-y-2">
    <p class="text-2xl font-semibold leading-tight">{{ __('ui.heading') }}</p>
    <p class="text-lg font-semibold">{{ __('ui.heading') }}</p>
    <p>{{ __('ui.body') }}</p>
    <p class="text-sm text-ink-muted">{{ __('ui.muted') }}</p>
    <p class="text-sm text-ink-muted">
        <span class="num font-semibold text-ink">@num(1234.5, 1)</span> {{ __('common.units.kcal') }}
        · <span class="num font-semibold text-ink">@num(12.25, 1)</span> {{ $g }}
        · @date(now(), 'long')
        · {{ Format::relativeDay(now()) }}
    </p>
</div>

{{-- Buttons --}}
<h2 class="{{ $h }}">{{ __('ui.sections.buttons') }}</h2>
<div class="flex flex-wrap items-center gap-2">
    <x-button>{{ __('ui.primary') }}</x-button>
    <x-button variant="secondary">{{ __('ui.secondary') }}</x-button>
    <x-button variant="ghost" icon="plus">{{ __('ui.ghost') }}</x-button>
    <x-button variant="danger" icon="trash">{{ __('ui.danger') }}</x-button>
    <x-button variant="danger-ghost">{{ __('ui.danger') }}</x-button>
    <x-button variant="subtle" iconEnd="chevron-end">{{ __('ui.subtle') }}</x-button>
</div>
<div class="mt-3 flex flex-wrap items-center gap-2">
    <x-button size="sm">{{ __('ui.small') }}</x-button>
    <x-button size="lg">{{ __('ui.large') }}</x-button>
    <x-button disabled>{{ __('ui.disabled') }}</x-button>
    <x-button variant="secondary" square icon="edit" aria-label="{{ __('common.edit') }}" />
    <x-button variant="ghost" square icon="mic" aria-label="mic" />
    <x-button square icon="camera" aria-label="camera" />
</div>
<div class="mt-3">
    <x-button block size="lg" icon="check">{{ __('ui.primary') }}</x-button>
</div>

{{-- Icons --}}
<h2 class="{{ $h }}">{{ __('ui.sections.icons') }}</h2>
<div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
    @foreach ($icons as $icon)
        <div class="flex min-w-0 flex-col items-center gap-1 rounded-control bg-surface-2 px-1 py-2.5">
            <x-icon :name="$icon" size="lg" />
            <span class="max-w-full truncate text-[0.625rem] text-ink-muted" dir="ltr">{{ $icon }}</span>
        </div>
    @endforeach
</div>

{{-- Forms --}}
<h2 class="{{ $h }}">{{ __('ui.sections.forms') }}</h2>
<div class="space-y-4">
    <x-input name="food-{{ $p }}" :label="__('ui.food_name')" :placeholder="__('ui.food_placeholder')" />
    <x-input name="search-{{ $p }}" type="search" icon="search" :placeholder="__('ui.search_placeholder')" aria-label="{{ __('common.search') }}" />
    <div class="grid grid-cols-2 gap-3">
        <x-input name="weight-{{ $p }}" type="number" inputmode="decimal" value="82.4" :label="__('ui.weight')" :suffix="__('common.units.kg')" />
        <x-select name="unit-{{ $p }}" :label="__('ui.unit')" :options="__('ui.units')" :placeholder="__('ui.choose_unit')" />
    </div>
    <x-input name="invalid-{{ $p }}" :label="__('ui.food_name')" :error="__('ui.error_example')" />
    <x-number-stepper name="quantity-{{ $p }}" :label="__('ui.quantity')" value="150" :step="10" :unit="$g" />
    <x-number-stepper name="pieces-{{ $p }}" :label="__('ui.quantity')" value="2" :step="0.5" />
    <x-textarea name="notes-{{ $p }}" :label="__('ui.notes')" :hint="__('ui.notes_hint')" optional />
    <x-segmented name="meal-{{ $p }}" :label="__('enums.meal.breakfast')" selected="lunch"
        :options="collect(App\Enums\Meal::cases())->mapWithKeys(fn ($meal) => [$meal->value => $meal->label()])->all()" />
    <x-checkbox name="favourite-{{ $p }}" :label="__('ui.remember')" />
</div>

{{-- Badges --}}
<h2 class="{{ $h }}">{{ __('ui.sections.badges') }}</h2>
<div class="flex flex-wrap items-center gap-2">
    <x-badge>{{ __('ui.category') }}</x-badge>
    <x-badge variant="accent">{{ __('ui.new') }}</x-badge>
    <x-badge variant="danger" icon="alert">{{ __('ui.over_limit') }}</x-badge>
    <x-badge variant="info">{{ __('enums.food_state.cooked') }}</x-badge>
    <x-badge estimate />
    @foreach (App\Enums\Confidence::cases() as $confidence)
        <x-badge :confidence="$confidence" />
    @endforeach
</div>

{{-- Surfaces --}}
<h2 class="{{ $h }}">{{ __('ui.sections.surfaces') }}</h2>
<div class="space-y-4">
    <x-page-header level="2" :title="__('ui.card_title')" :subtitle="__('ui.card_subtitle')">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" icon="plus">{{ __('common.add') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card padding="none" class="divide-y divide-line overflow-hidden">
        <x-list-row :title="__('ui.row_1')" :subtitle="__('ui.row_1_sub')" :value="Format::number(1.2, 1)" :valueHint="__('ui.net_carbs_short')" href="#" :navigate="false" />
        <x-list-row :title="__('ui.row_2')" :subtitle="__('ui.row_2_sub')" :value="Format::number(1.1, 1)" :valueHint="__('ui.net_carbs_short')" icon="flame">
            <x-badge estimate />
        </x-list-row>
        <x-list-row as="button" icon="plus" :title="__('ui.row_3')" :subtitle="__('ui.row_3_sub')">
            <x-slot:trailing>
                <x-badge :confidence="App\Enums\Confidence::High" />
            </x-slot:trailing>
        </x-list-row>
    </x-card>

    <x-card :title="__('ui.card_title')" :subtitle="__('ui.card_subtitle')">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" square icon="more" aria-label="{{ __('ui.subtle') }}" />
        </x-slot:actions>
        <div class="grid grid-cols-2 gap-4">
            <x-stat :label="__('ui.stat_weight')" :value="82.4" :decimals="1" :unit="__('common.units.kg')" :delta="-0.6" :deltaUnit="__('common.units.kg')" deltaGood="down" :hint="__('ui.stat_hint')" icon="scale" />
            <x-stat :label="__('ui.stat_calories')" :value="1240" :unit="__('common.units.kcal')" :hint="__('ui.stat_of')" icon="flame" lowerBound />
        </div>
        <x-slot:footer>
            <x-button variant="ghost" size="sm" iconEnd="chevron-end">{{ __('common.view_all') }}</x-button>
        </x-slot:footer>
    </x-card>

    <x-card muted>
        <x-page-header :title="__('ui.heading')" :subtitle="Format::date(now(), 'weekday')" back="#" class="!mb-0" />
    </x-card>
</div>

{{-- Tabs --}}
<h2 class="{{ $h }}">{{ __('ui.sections.tabs') }}</h2>
<x-tabs :tabs="['macros-'.$p => __('ui.tab_macros'), 'weight-'.$p => ['label' => __('ui.tab_weight'), 'count' => 8], 'history-'.$p => __('ui.tab_history')]" :label="__('ui.sections.tabs')">
    <x-tabs.panel name="macros-{{ $p }}"><p class="text-ink-muted">{{ __('ui.tab_content', ['tab' => __('ui.tab_macros')]) }}</p></x-tabs.panel>
    <x-tabs.panel name="weight-{{ $p }}"><p class="text-ink-muted">{{ __('ui.tab_content', ['tab' => __('ui.tab_weight')]) }}</p></x-tabs.panel>
    <x-tabs.panel name="history-{{ $p }}"><p class="text-ink-muted">{{ __('ui.tab_content', ['tab' => __('ui.tab_history')]) }}</p></x-tabs.panel>
</x-tabs>

{{-- Feedback --}}
<h2 class="{{ $h }}">{{ __('ui.sections.feedback') }}</h2>
<div class="space-y-3">
    <x-alert>{{ __('ui.alert_info') }}</x-alert>
    <x-alert variant="success">{{ __('ui.toast_success') }}</x-alert>
    <x-alert variant="warning" :title="__('ui.alert_warning_title')" dismissible>{{ __('ui.alert_warning') }}</x-alert>
    <x-alert variant="danger">{{ __('ui.alert_danger') }}</x-alert>
    <div class="flex flex-wrap gap-2" x-data>
        <x-button variant="secondary" x-on:click="$dispatch('toast', { message: {{ Js::from(__('ui.toast_success')) }} })">{{ __('ui.show_success') }}</x-button>
        <x-button variant="secondary" x-on:click="$dispatch('toast', { message: {{ Js::from(__('ui.toast_error')) }}, type: 'error' })">{{ __('ui.show_error') }}</x-button>
    </div>
</div>

{{-- Overlays --}}
<h2 class="{{ $h }}">{{ __('ui.sections.overlays') }}</h2>
<div class="flex flex-wrap gap-2" x-data>
    <x-button variant="secondary" x-on:click="$dispatch('open-modal', 'demo-modal-{{ $p }}')">{{ __('ui.open_modal') }}</x-button>
    <x-button variant="secondary" x-on:click="$dispatch('open-modal', 'demo-sheet-{{ $p }}')">{{ __('ui.open_sheet') }}</x-button>
</div>

{{-- /_ui?open=modal or ?open=sheet renders the Arabic one already open (handy for screenshots) --}}
<x-modal name="demo-modal-{{ $p }}" :title="__('ui.modal_title')" size="sm" :show="request('open') === 'modal' && $p === 'ar'">
    <p class="text-ink-muted">{{ __('ui.modal_text') }}</p>
    <x-slot:footer>
        <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'demo-modal-{{ $p }}')">{{ __('common.cancel') }}</x-button>
        <x-button variant="danger" x-on:click="$dispatch('close-modal', 'demo-modal-{{ $p }}')">{{ __('common.delete') }}</x-button>
    </x-slot:footer>
</x-modal>

<x-bottom-sheet name="demo-sheet-{{ $p }}" :title="__('ui.sheet_title')" :description="__('ui.row_1')" :show="request('open') === 'sheet' && $p === 'ar'">
    <div class="space-y-4">
        <x-number-stepper name="sheet-quantity-{{ $p }}" :label="__('ui.quantity')" value="2" :step="0.5" />
        <x-select name="sheet-unit-{{ $p }}" :label="__('ui.unit')" :options="__('ui.units')" selected="tbsp" />
    </div>
    <x-slot:footer>
        <x-button block size="lg" x-on:click="$dispatch('close-modal', 'demo-sheet-{{ $p }}')">{{ __('common.add') }}</x-button>
    </x-slot:footer>
</x-bottom-sheet>

{{-- States --}}
<h2 class="{{ $h }}">{{ __('ui.sections.states') }}</h2>
<div class="space-y-4">
    <x-card>
        <x-empty-state icon="flame" :title="__('ui.empty_title')" :text="__('ui.empty_text')" compact>
            <x-button icon="plus">{{ __('ui.empty_action') }}</x-button>
        </x-empty-state>
    </x-card>
    <x-card padding="none" class="divide-y divide-line">
        <x-skeleton shape="row" />
        <x-skeleton shape="row" />
    </x-card>
    <div class="flex items-center gap-4">
        <x-skeleton shape="circle" class="size-16" />
        <div class="flex-1 space-y-3">
            <x-skeleton shape="title" />
            <x-skeleton :lines="2" />
        </div>
    </div>
</div>

{{-- Macros --}}
<h2 class="{{ $h }}">{{ __('ui.sections.macros') }}</h2>
<x-card>
    <div class="grid grid-cols-3 gap-2">
        <x-macro-ring :label="__('common.macros.net_carbs')" macro="carbs" :value="12" :target="20" :unit="$g" />
        <x-macro-ring :label="__('common.macros.calories')" macro="kcal" :value="1960" :target="1800" :unit="__('common.units.kcal')" />
        <x-macro-ring :label="__('common.macros.protein')" macro="protein" :value="68" :target="95" :unit="$g" :limit="false" lowerBound />
    </div>
    <div class="mt-5 flex items-end justify-around gap-2">
        <x-macro-ring size="sm" :label="__('common.macros.fat')" macro="fat" :value="90" :target="130" :unit="$g" />
        <x-macro-ring size="sm" :label="__('common.macros.fiber')" macro="fiber" :value="9" :unit="$g" />
        <x-macro-ring size="sm" :label="__('common.macros.protein')" macro="protein" :value="96" :target="95" :unit="$g" :limit="false" />
    </div>
    <div class="mt-5 flex justify-center">
        <x-macro-ring size="lg" :label="__('common.macros.net_carbs')" macro="carbs" :value="23.5" :target="20" :decimals="1" :unit="$g" />
    </div>
    <div class="mt-6 space-y-4">
        <x-macro-bar :label="__('common.macros.net_carbs')" macro="carbs" :value="12" :target="20" :unit="$g" />
        <x-macro-bar :label="__('common.macros.protein')" macro="protein" :value="68" :target="95" :unit="$g" :limit="false" lowerBound />
        <x-macro-bar :label="__('common.macros.fat')" macro="fat" :value="131" :target="130" :unit="$g" :limit="false" />
        <x-macro-bar :label="__('common.macros.calories')" macro="kcal" :value="1960" :target="1800" :unit="__('common.units.kcal')" />
        <x-macro-bar :label="__('common.macros.fiber')" macro="fiber" :value="9" :unit="$g" lowerBound />
    </div>
</x-card>

{{-- Charts --}}
<h2 class="{{ $h }}">{{ __('ui.sections.charts') }}</h2>
<div class="space-y-4">
    <x-card :title="__('ui.weight_trend')">
        <x-chart.line :points="$weights" :labels="$weightLabels" :target="80" :unit="__('common.units.kg')" :title="__('ui.weight_trend')" />
    </x-card>
    <x-card :title="__('ui.carbs_week')">
        <x-chart.bars :points="$carbs" :target="20" macro="carbs" :unit="$g" :highlight="6" :title="__('ui.carbs_week')" />
    </x-card>
    <x-card>
        <x-chart.line :points="[]" :title="__('ui.weight_trend')" :height="96" />
    </x-card>
</div>
