<?php

namespace App\Livewire\Foods;

use App\Enums\CarbBasis;
use App\Enums\FoodCategory;
use App\Enums\FoodState;
use App\Enums\Preparation;
use App\Livewire\Concerns\Toasts;
use App\Models\Food;
use App\Services\Food\CustomFoodService;
use App\Services\Food\FoodInUseException;
use App\View\Format;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create / edit a private ingredient. All validation, the net→total carb conversion and the
 * per-serving → per-100 g scaling live in CustomFoodService; this form only collects input.
 * Property names equal the service's data keys so its validation errors land on the right field.
 */
class Form extends Component
{
    use AuthorizesRequests, Toasts;

    #[Locked]
    public ?int $foodId = null;

    public string $name_ar = '';

    public string $name_en = '';

    public string $category = 'other';

    public string $state = 'na';

    public string $preparation = '';

    public string $cooked_yield = '';

    /** 'per100' or 'serving' – with 'serving', per_grams is the size the label values refer to. */
    public string $basisMode = 'per100';

    public string $per_grams = '100';

    public string $calories = '';

    public string $protein_g = '';

    public string $carbs_g = '';

    public string $fiber_g = '';

    public string $fat_g = '';

    public string $saturated_fat_g = '';

    public string $label_basis = 'total';

    public bool $is_estimate = false;

    public string $notes_ar = '';

    public string $notes_en = '';

    /** One alias per line or comma-separated. */
    public string $aliasesText = '';

    /** @var list<array{unit: string, label: string, grams: string, is_default: bool}> */
    public array $units = [];

    public ?string $formError = null;

    public ?string $deleteError = null;

    public function mount(?Food $food = null): void
    {
        if ($food?->exists) {
            $this->authorize('update', $food);
            $this->foodId = $food->id;
            $this->fill([
                'name_ar' => (string) $food->name_ar,
                'name_en' => (string) $food->name_en,
                'category' => $food->category?->value ?? 'other',
                'state' => $food->state?->value ?? 'na',
                'preparation' => $food->preparation?->value ?? '',
                'cooked_yield' => $this->str($food->cooked_yield),
                'calories' => $this->str($food->calories),
                'protein_g' => $this->str($food->protein_g),
                'carbs_g' => $this->str($food->carbs_g),
                'fiber_g' => $this->str($food->fiber_g),
                'fat_g' => $this->str($food->fat_g),
                'saturated_fat_g' => $this->str($food->saturated_fat_g),
                'is_estimate' => (bool) $food->is_estimate,
                'notes_ar' => (string) $food->notes_ar,
                'notes_en' => (string) $food->notes_en,
                'aliasesText' => $food->aliases()->orderBy('id')->pluck('alias')->implode("\n"),
                'units' => $food->units()->orderBy('id')->get()->map(fn ($u) => [
                    'unit' => $u->unit, 'label' => $u->label(), 'grams' => $this->str($u->grams), 'is_default' => (bool) $u->is_default,
                ])->all(),
            ]);

            return;
        }

        $this->authorize('create', Food::class);
        $this->name_ar = app()->getLocale() === 'ar' ? trim((string) request()->query('name', '')) : '';
        $this->name_en = app()->getLocale() === 'ar' ? '' : trim((string) request()->query('name', ''));
    }

    public function addUnit(): void
    {
        $taken = array_column($this->units, 'unit');
        $n = count($this->units) + 1;
        while (in_array('u'.$n, $taken, true)) {
            $n++;
        }
        $this->units[] = ['unit' => 'u'.$n, 'label' => '', 'grams' => '', 'is_default' => $this->units === []];
    }

    public function removeUnit(int $index): void
    {
        unset($this->units[$index]);
        $this->units = array_values($this->units);
        if ($this->units !== [] && ! collect($this->units)->contains('is_default', true)) {
            $this->units[0]['is_default'] = true;
        }
    }

    public function makeDefault(int $index): void
    {
        foreach ($this->units as $i => $unit) {
            $this->units[$i]['is_default'] = $i === $index;
        }
    }

    public function save(CustomFoodService $service)
    {
        $this->formError = null;
        $this->resetErrorBag();

        try {
            if ($this->foodId === null) {
                $this->authorize('create', Food::class);
                $food = $service->create(auth()->user(), $this->payload());
            } else {
                $existing = Food::query()->findOrFail($this->foodId);
                $this->authorize('update', $existing);
                $food = $service->update($existing, $this->payload());
            }
        } catch (ValidationException $e) {
            // keys that have no visible field (macros, aliases.N …) are shown as one alert
            $this->setErrorBag($e->validator->errors());
            $this->formError = collect($e->errors())->only(['macros'])->flatten()->first()
                ?? collect($e->errors())->filter(fn ($m, $k) => str_starts_with($k, 'aliases'))->flatten()->first();

            return;
        } catch (AuthorizationException $e) {
            $this->formError = $e->getMessage() ?: __('foods.errors.not_accessible');

            return;
        }

        $this->flashToast($this->foodId === null ? __('foods.created') : __('foods.updated'));

        return $this->redirectRoute('foods.show', ['food' => $food->id], navigate: true);
    }

    public function delete(CustomFoodService $service)
    {
        $food = Food::query()->findOrFail($this->foodId);
        $this->authorize('delete', $food);

        try {
            $service->delete($food);
        } catch (FoodInUseException|AuthorizationException $e) {
            $this->deleteError = $e->getMessage();
            $this->dispatch('close-modal', name: 'delete-food');

            return;
        }

        $this->flashToast(__('foods.deleted'));

        return $this->redirectRoute('foods.index', navigate: true);
    }

    public function render(CustomFoodService $service)
    {
        $warnings = [];
        $numeric = fn (string $v) => trim($v) !== '' && is_numeric($v);
        if ($numeric($this->calories) && $numeric($this->protein_g) && $numeric($this->carbs_g) && $numeric($this->fat_g)) {
            try {
                $warnings = $service->check(array_merge($this->payload(), ['name_ar' => $this->name_ar ?: ($this->name_en ?: '-')]));
            } catch (ValidationException) {
                // hard errors are reported on save, the live hint only shows soft warnings
            }
        }

        $editing = $this->foodId !== null;

        return view('livewire.foods.form', [
            'editing' => $editing,
            'warnings' => $warnings,
            'categoryOptions' => collect(FoodCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'stateOptions' => collect(FoodState::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'preparationOptions' => ['' => __('common.none')] + collect(Preparation::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'basisOptions' => collect(CarbBasis::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
        ])->title($editing ? __('foods.form.edit_title') : __('foods.form.create_title'))
            ->layoutData(['back' => $editing ? route('foods.show', $this->foodId) : route('foods.index')]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $num = fn (string $v) => trim($v) === '' ? null : str_replace(',', '.', Format::westernDigits(trim($v)));
        $perServing = $this->basisMode === 'serving';
        $aliases = array_values(array_filter(array_map('trim', preg_split('/[\n,،]+/u', $this->aliasesText) ?: [])));

        $units = [];
        foreach ($this->units as $unit) {
            if (trim($unit['label']) === '' && trim((string) $unit['grams']) === '') {
                continue;
            }
            $label = trim($unit['label']);
            $units[] = [
                'unit' => $unit['unit'],
                'label_ar' => app()->getLocale() === 'ar' ? $label : '',
                'label_en' => app()->getLocale() === 'ar' ? '' : $label,
                'grams' => $num((string) $unit['grams']),
                'is_default' => (bool) $unit['is_default'],
            ];
        }

        return [
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'category' => $this->category ?: null,
            'state' => $this->state ?: null,
            'preparation' => $this->preparation ?: null,
            'cooked_yield' => $this->state === 'raw' ? $num($this->cooked_yield) : null,
            'calories' => $num($this->calories),
            'protein_g' => $num($this->protein_g),
            'carbs_g' => $num($this->carbs_g),
            'fiber_g' => $num($this->fiber_g),
            'fat_g' => $num($this->fat_g),
            'saturated_fat_g' => $num($this->saturated_fat_g),
            'label_basis' => $this->label_basis,
            'per_grams' => $perServing ? $num($this->per_grams) : 100,
            'is_estimate' => $this->is_estimate,
            'notes_ar' => $this->notes_ar ?: null,
            'notes_en' => $this->notes_en ?: null,
            'aliases' => $aliases,
            'units' => $units,
        ];
    }

    private function str(?float $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
