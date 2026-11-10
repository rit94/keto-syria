<?php

use App\Models\Food;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;

beforeEach(function () {
    $this->resolver = app(UnitResolver::class);
    $this->egg = Food::factory()->create();
    $this->egg->units()->createMany([
        ['unit' => 'piece', 'label_ar' => 'حبة', 'label_en' => 'piece', 'grams' => 50, 'is_default' => true],
        ['unit' => 'cup', 'label_ar' => 'كوب', 'label_en' => 'cup', 'grams' => 243.5],
    ]);
});

it('resolves the universal mass units for any food', function (string $unit, float $quantity, float $grams) {
    expect($this->resolver->toGrams(Food::factory()->create(), $quantity, $unit))->toEqualWithDelta($grams, 1e-9);
})->with([
    'grams' => ['g', 150.0, 150.0],
    'fractional grams' => ['g', 0.1, 0.1],
    'kilograms' => ['kg', 1.25, 1250.0],
    'ounces' => ['oz', 1.0, 28.349523125],
    'several ounces' => ['oz', 4.0, 113.3980925],
    'syrian ouqiya' => ['ouqiya', 1.0, 200.0],
    'half an ouqiya' => ['ouqiya', 0.5, 100.0],
]);

it('resolves a household unit defined for the food', function () {
    expect($this->resolver->toGrams($this->egg, 2, 'piece'))->toBe(100.0)
        ->and($this->resolver->toGrams($this->egg, 0.5, 'cup'))->toBe(121.75);
});

it('ignores case and surrounding whitespace in the unit key', function () {
    expect($this->resolver->toGrams($this->egg, 1, ' KG '))->toBe(1000.0)
        ->and($this->resolver->toGrams($this->egg, 1, 'Piece'))->toBe(50.0);
});

it('rejects a household unit the food does not define', function () {
    $this->resolver->toGrams($this->egg, 1, 'slice');
})->throws(UnknownUnitException::class);

it("does not borrow another food's household unit", function () {
    $this->resolver->toGrams(Food::factory()->create(), 1, 'piece');
})->throws(UnknownUnitException::class);

it('carries the unit and food on the exception', function () {
    try {
        $this->resolver->toGrams($this->egg, 1, 'handful');
        $this->fail('expected UnknownUnitException');
    } catch (UnknownUnitException $e) {
        expect($e->unit)->toBe('handful')->and($e->foodId)->toBe($this->egg->id)->and($e->getMessage())->toContain('handful');
    }
});

it('rejects a quantity that is not finite and greater than zero', function (float $quantity) {
    $this->resolver->toGrams($this->egg, $quantity, 'g');
})->with([0.0, -1.0, -0.001, NAN, INF, -INF])->throws(InvalidQuantityException::class);

it('lists universal units first, then the units of the food', function () {
    app()->setLocale('en');
    $units = $this->resolver->unitsFor($this->egg->fresh());

    expect(array_column($units, 'unit'))->toBe(['g', 'kg', 'oz', 'ouqiya', 'piece', 'cup'])
        ->and(array_column($units, 'grams'))->toBe([1.0, 1000.0, 28.349523125, 200.0, 50.0, 243.5])
        ->and($units[0])->toBe(['unit' => 'g', 'label' => 'g', 'grams' => 1.0, 'is_default' => false])
        ->and($units[4])->toBe(['unit' => 'piece', 'label' => 'piece', 'grams' => 50.0, 'is_default' => true]);
});

it('localises the labels', function () {
    app()->setLocale('ar');
    $labels = array_column($this->resolver->unitsFor($this->egg), 'label', 'unit');

    expect($labels['g'])->toBe('غ')
        ->and($labels['kg'])->toBe('كغ')
        ->and($labels['ouqiya'])->toContain('أوقية')
        ->and($labels['piece'])->toBe('حبة');

    app()->setLocale('en');
    $labels = array_column($this->resolver->unitsFor($this->egg), 'label', 'unit');

    expect($labels['kg'])->toBe('kg')->and($labels['piece'])->toBe('piece');
});

it('has a translation for every universal unit in both locales', function (string $locale) {
    foreach ([...array_keys(UnitResolver::UNIVERSAL), 'serving'] as $unit) {
        expect(trans()->has("units.$unit", $locale, false))->toBeTrue("units.$unit missing for $locale");
    }
})->with(['ar', 'en']);

it('returns only the universal units for a food without household units', function () {
    expect(array_column($this->resolver->unitsFor(Food::factory()->create()), 'unit'))->toBe(['g', 'kg', 'oz', 'ouqiya']);
});
