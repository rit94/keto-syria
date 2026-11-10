<?php

namespace App\Livewire\Coach\Support;

use App\Models\CoachProposal;
use App\Models\Food;
use App\Models\Recipe;
use App\Services\Ai\Proposals\ProposalType;
use App\Services\Food\UnitResolver;
use Illuminate\Support\Facades\Auth;

/**
 * Edit-before-confirm helper: copies a proposal's editable payload keys into form state and
 * turns the form state back into the `$edits` array ProposalService understands. It never
 * calculates anything: previews come from ProposalService::preview().
 */
class ProposalEditor
{
    /** @var array<int, ?Food> */
    private array $foods = [];

    /** @var array<int, ?Recipe> */
    private array $recipes = [];

    /** @return array<string, mixed> form state */
    public function initial(CoachProposal $proposal): array
    {
        $payload = $proposal->payload ?? [];

        return match ($proposal->type) {
            ProposalType::LogMeal => [
                'date' => (string) ($payload['date'] ?? ''),
                'meal' => (string) ($payload['meal'] ?? ''),
                'items' => array_map(fn (array $item) => [
                    'food_id' => $item['food_id'],
                    'quantity' => $this->tidy($item['quantity'] ?? null),
                    'unit' => (string) ($item['unit'] ?? 'g'),
                    'weighed_cooked' => (bool) ($item['weighed_cooked'] ?? false),
                ], array_values($payload['items'] ?? [])),
            ],
            ProposalType::UpdateLogEntry => $this->updateState($proposal),
            ProposalType::SaveRecipe => [
                'name' => (string) ($payload['name'] ?? ''),
                'description' => (string) ($payload['description'] ?? ''),
                'instructions' => (string) ($payload['instructions'] ?? ''),
                'servings' => $this->tidy($payload['servings'] ?? null),
                'cooked_weight_g' => $this->tidy($payload['cooked_weight_g'] ?? null),
                'ingredients' => array_map(fn (array $row) => [
                    'food_id' => $row['food_id'],
                    'quantity' => $this->tidy($row['quantity'] ?? null),
                    'unit' => (string) ($row['unit'] ?? 'g'),
                ], array_values($payload['ingredients'] ?? [])),
            ],
            ProposalType::MealPlan => [
                'name' => (string) ($payload['name'] ?? ''),
                'starts_on' => (string) ($payload['starts_on'] ?? ''),
                'days' => $this->tidy($payload['days'] ?? null),
                'items' => array_map(fn (array $row) => [
                    'planned_on' => (string) ($row['planned_on'] ?? ''),
                    'meal' => (string) ($row['meal'] ?? ''),
                    'food_id' => $row['food_id'] ?? null,
                    'recipe_id' => $row['recipe_id'] ?? null,
                    'quantity' => $this->tidy($row['quantity'] ?? null),
                    'unit' => (string) ($row['unit'] ?? ''),
                    'note' => $row['note'] ?? null,
                ], array_values($payload['items'] ?? [])),
            ],
            ProposalType::DeleteLogEntry => [],
        };
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed> only the keys ProposalType::editableKeys() allows
     */
    public function edits(CoachProposal $proposal, array $state): array
    {
        $edits = [];

        foreach ($proposal->type->editableKeys() as $key) {
            if (! array_key_exists($key, $state)) {
                continue;
            }

            $edits[$key] = match ($key) {
                'items', 'ingredients' => array_values(array_map(fn ($row) => $this->row(is_array($row) ? $row : []), (array) $state[$key])),
                'changes' => $this->changes((array) $state[$key]),
                'servings', 'cooked_weight_g', 'days' => $this->number($state[$key]),
                default => $state[$key],
            };
        }

        // a blank optional weight means "not measured", not "0"
        if (array_key_exists('cooked_weight_g', $edits) && ($edits['cooked_weight_g'] === '' || $edits['cooked_weight_g'] === null)) {
            $edits['cooked_weight_g'] = null;
        }

        return $edits;
    }

    /** @return array<string, string> unit => label, for the unit select of one row */
    public function unitOptions(?int $foodId, ?int $recipeId = null): array
    {
        if ($recipeId !== null) {
            return ['serving' => __('coach_ui.proposal.unit_serving'), 'g' => __('common.units.g')];
        }

        $food = $foodId === null ? null : $this->food($foodId);
        if ($food === null) {
            return ['g' => __('common.units.g')];
        }

        $options = [];
        foreach (app(UnitResolver::class)->unitsFor($food) as $unit) {
            $options[$unit['unit']] = $unit['label'];
        }

        return $options;
    }

    /** Label of a unit key for display (falls back to the key itself). */
    public function unitLabel(?int $foodId, ?int $recipeId, ?string $unit): string
    {
        if ($unit === null || $unit === '') {
            return '';
        }

        return $this->unitOptions($foodId, $recipeId)[UnitResolver::normalize($unit)] ?? $unit;
    }

    public function canWeighCooked(?int $foodId): bool
    {
        $food = $foodId === null ? null : $this->food($foodId);

        return $food !== null && $food->state?->value === 'raw' && $food->cooked_yield !== null;
    }

    /** Display name of a row's food / recipe. */
    public function label(?int $foodId, ?int $recipeId = null): string
    {
        $unknown = __('coach_ui.proposal.unknown_item');

        if ($recipeId !== null) {
            return $this->recipe($recipeId)?->name ?? $unknown;
        }

        return $foodId === null ? $unknown : ($this->food($foodId)?->displayName() ?? $unknown);
    }

    /** @return array<string, mixed> */
    private function updateState(CoachProposal $proposal): array
    {
        $after = $proposal->preview['after'] ?? [];

        return ['changes' => [
            'quantity' => $this->tidy($after['quantity'] ?? null),
            'unit' => (string) ($after['unit'] ?? ''),
            'meal' => (string) ($after['meal'] ?? ''),
            'logged_on' => (string) ($after['date'] ?? ''),
        ]];
    }

    private function food(int $id): ?Food
    {
        return $this->foods[$id] ??= Food::query()->visibleTo(Auth::user())->with('units')->find($id);
    }

    private function recipe(int $id): ?Recipe
    {
        return $this->recipes[$id] ??= Recipe::query()->where('user_id', Auth::id())->find($id);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row): array
    {
        $out = [];

        foreach ($row as $key => $value) {
            $out[$key] = match ($key) {
                'food_id', 'recipe_id' => is_numeric($value) ? (int) $value : null,
                'quantity' => $this->number($value),
                'weighed_cooked' => (bool) $value,
                default => $value,
            };
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed> blank fields dropped (they mean "leave as it is")
     */
    private function changes(array $changes): array
    {
        $out = [];

        foreach (['quantity', 'unit', 'meal', 'logged_on'] as $key) {
            $value = $changes[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $out[$key] = $key === 'quantity' ? $this->number($value) : $value;
        }

        return $out;
    }

    /** Numeric strings (Western or Arabic-Indic digits, comma decimals) become numbers; anything else is left for the validator to reject. */
    private function number(mixed $value): mixed
    {
        if (is_string($value)) {
            $map = [
                "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3', "\u{0664}" => '4',
                "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7', "\u{0668}" => '8', "\u{0669}" => '9',
                "\u{066B}" => '.', "\u{060C}" => '.', ',' => '.',
            ];
            $value = strtr(trim($value), $map);
        }

        return is_numeric($value) ? $value + 0 : $value;
    }

    private function tidy(mixed $value): int|float|string
    {
        if (! is_numeric($value)) {
            return '';
        }

        $value = (float) $value;

        return floor($value) === $value ? (int) $value : round($value, 3);
    }
}
