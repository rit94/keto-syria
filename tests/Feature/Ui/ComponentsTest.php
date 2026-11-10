<?php

use App\Enums\Confidence;
use App\View\Chart;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    // Anonymous components read $errors, which the web middleware normally shares.
    view()->share('errors', new ViewErrorBag);
});

it('wires button loading state to the clicked Livewire action', function () {
    $html = Blade::render('<x-button wire:click="save(3)">Save</x-button>');

    expect($html)
        ->toContain('type="button"')
        ->toContain('wire:loading.attr="aria-busy"')
        ->toContain('wire:target="save(3)"')
        ->toContain('min-h-touch');

    $submit = Blade::render('<x-button type="submit" loading="login">Go</x-button>');
    expect($submit)->toContain('type="submit"')->toContain('wire:target="login"');

    $plain = Blade::render('<x-button>Plain</x-button>');
    expect($plain)->not->toContain('wire:loading');

    $link = Blade::render('<x-button href="/x" variant="secondary">Link</x-button>');
    expect($link)->toContain('<a href="/x"')->not->toContain('<button');
});

it('flips directional icons only', function () {
    expect(Blade::render('<x-icon name="chevron-end" />'))->toContain('rtl:-scale-x-100')
        ->and(Blade::render('<x-icon name="plus" />'))->not->toContain('rtl:-scale-x-100')
        ->and(Blade::render('<x-icon name="plus" />'))->toContain('aria-hidden="true"');
});

it('derives input name, id and error from wire:model', function () {
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => ['bad email']]));
    view()->share('errors', $errors);

    $html = Blade::render('<x-input wire:model="email" type="email" label="Email" />');

    expect($html)
        ->toContain('name="email"')
        ->toContain('id="email"')
        ->toContain('for="email"')
        ->toContain('aria-invalid="true"')
        ->toContain('aria-describedby="email-error"')
        ->toContain('bad email')
        ->toContain('wire:model="email"');
});

it('renders the estimate and confidence badges in the current language', function () {
    app()->setLocale('ar');
    expect(Blade::render('<x-badge estimate />'))->toContain('تقديري');
    expect(Blade::render('<x-badge :confidence="$c" />', ['c' => Confidence::Low]))->toContain('تقريبي');

    app()->setLocale('en');
    expect(Blade::render('<x-badge estimate />'))->toContain('Estimate');
    expect(Blade::render('<x-badge confidence="high" />'))->toContain('Verified');
});

it('shows remaining, over-target and lower-bound states on the macro bar', function () {
    app()->setLocale('en');

    $under = Blade::render('<x-macro-bar label="Net carbs" macro="carbs" :value="12" :target="20" unit="g" />');
    expect($under)->toContain('8 g left')->toContain('width: 60%')->toContain('bg-macro-carbs')->not->toContain('bg-danger');

    $over = Blade::render('<x-macro-bar label="Net carbs" macro="carbs" :value="25" :target="20" unit="g" />');
    expect($over)->toContain('5 g over')->toContain('bg-danger')->toContain('width: 100%');

    $goal = Blade::render('<x-macro-bar label="Protein" macro="protein" :value="110" :target="95" unit="g" :limit="false" />');
    expect($goal)->not->toContain('bg-danger')->not->toContain('g over');

    $bound = Blade::render('<x-macro-bar label="Fiber" macro="fiber" :value="9" :target="25" unit="g" :limit="false" lowerBound />');
    expect($bound)->toContain('≥ 9')->toContain('At least');

    // The engine's remaining figure wins over the displayed difference.
    $engine = Blade::render('<x-macro-bar label="Net carbs" :value="12" :target="20" :left="7.6" :decimals="1" unit="g" />');
    expect($engine)->toContain('7.6 g left');
});

it('shows over-target and no-target states on the macro ring', function () {
    app()->setLocale('en');

    $over = Blade::render('<x-macro-ring label="Calories" :value="1960" :target="1800" unit="kcal" />');
    expect($over)->toContain('stroke-danger')->toContain('160 kcal over')->toContain('1,960');

    $none = Blade::render('<x-macro-ring label="Fiber" macro="fiber" :value="9" unit="g" />');
    expect($none)->toContain('No target')->not->toContain('stroke-dasharray');

    app()->setLocale('ar');
    $arabic = Blade::render('<x-macro-ring label="الكربوهيدرات" macro="carbs" :value="12" :target="20" unit="غ" />');
    expect($arabic)->toContain('متبقٍ 8 غ')->not->toMatch('/[٠-٩]/u');
});

it('renders charts from plain arrays', function () {
    app()->setLocale('en');

    $line = Blade::render('<x-chart.line :points="$p" :target="80" unit="kg" title="Weight" />', [
        'p' => ['W1' => 84.6, 'W2' => 83.9, 'W3' => null, 'W4' => 82.8],
    ]);

    expect($line)
        ->toContain('<svg')
        ->toContain('rtl:-scale-x-100')
        ->toContain('Target: 80 kg')
        ->toContain('From 82.8 kg to 84.6 kg')
        ->toContain('W4');

    $bars = Blade::render('<x-chart.bars :points="[18, 22, 14]" :labels="[\'Sat\', \'Sun\', \'Mon\']" :target="20" unit="g" />');
    expect($bars)->toContain('bg-danger')->toContain('Sun')->toContain('Target: 20 g');

    $empty = Blade::render('<x-chart.line :points="[]" />');
    expect($empty)->toContain(__('common.chart.empty'))->not->toContain('<svg');
});

it('computes a tidy chart axis', function () {
    [$min, $max, $ticks] = Chart::axis([82.4, 84.1]);
    expect($min)->toBe(82.0)->and($max)->toBe(85.0)->and($ticks)->toBe([82.0, 83.0, 84.0, 85.0]);

    [$min, $max] = Chart::axis([12, 27, 20], fromZero: true, min: 0.0);
    expect($min)->toBe(0.0)->and($max)->toBeGreaterThanOrEqual(27.0);

    [$min, $max] = Chart::axis([]);
    expect($max)->toBeGreaterThan($min);

    expect(Chart::series(['a' => 1, 'b' => null]))->toBe([
        ['label' => 'a', 'value' => 1.0],
        ['label' => 'b', 'value' => null],
    ])->and(Chart::labelIndexes(30, 6))->toHaveCount(6)->toContain(0)->toContain(29);
});

it('gives modals an accessible dialog with open and close hooks', function () {
    $html = Blade::render('<x-bottom-sheet name="pick" title="Pick one">body<x-slot:footer>foot</x-slot:footer></x-bottom-sheet>');

    expect($html)
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('aria-labelledby="modal-pick-title"')
        ->toContain('x-trap.noscroll.inert="open"')
        ->toContain('x-on:open-modal.window="onOpen($event)"')
        ->toContain('x-on:keydown.escape.window="dismiss()"')
        ->toContain('rounded-t-sheet')
        ->toContain('foot');
});

it('renders a segmented control as a labelled radio group bound to wire:model', function () {
    $html = Blade::render('<x-segmented wire:model.live="meal" label="Meal" :options="[\'lunch\' => \'Lunch\', \'dinner\' => \'Dinner\']" />');

    expect($html)
        ->toContain('<fieldset')
        ->toContain('<legend')
        ->toContain('name="meal"')
        ->toContain('value="dinner"')
        ->toContain('wire:model.live="meal"');
});
