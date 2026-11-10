<?php

namespace App\Services\Ai\Tools;

use App\Domain\Nutrition\NutritionCalculator;
use App\Models\CoachConversation;
use App\Models\Food;
use App\Models\User;
use App\Services\Ai\Support\FoodPresenter;
use App\Services\Food\InvalidQuantityException;
use App\Services\Food\UnitResolver;
use App\Services\Food\UnknownUnitException;
use App\Services\Logging\FoodLogService;
use App\Services\Logging\LoggingException;

class CalculateNutritionTool extends AbstractTool
{
    public function __construct(
        private readonly FoodLogService $log,
        private readonly NutritionCalculator $calculator,
        private readonly UnitResolver $units,
        private readonly FoodPresenter $presenter,
    ) {}

    public function name(): string
    {
        return 'calculate_nutrition';
    }

    public function description(): string
    {
        return 'Calculate calories and macros for specific amounts of foods with the nutrition engine. Saves nothing. '
            .'Use this for every number you tell the user (a meal idea, "how much is in ...", comparing options) instead of doing arithmetic yourself. '
            .'Returns nutrition per item, the total, and per serving when servings is given. Items that cannot be calculated come back with an error '
            .'(for example an unknown unit, with the list of valid units) and are left out of the total; "complete" is false in that case. '
            .'"missing" lists nutrients that are unknown for some item, so the matching totals are lower bounds.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'items' => ['type' => 'array', 'items' => self::foodAmountSchema(), 'description' => 'One entry per food amount.'],
            'servings' => ['type' => 'number', 'description' => 'Optional: divide the total into this many equal servings.'],
        ], ['items']);
    }

    protected function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.food_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.weighed_cooked' => ['sometimes', 'boolean'],
            'servings' => ['sometimes', 'numeric', 'gt:0', 'max:1000'],
        ];
    }

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $items = array_values($data['items']);
        $foods = Food::query()->visibleTo($user)->with('units')
            ->whereIn('id', array_map('intval', array_column($items, 'food_id')))
            ->get()->keyBy('id');

        $rows = [];
        $parts = [];
        $estimate = false;

        foreach ($items as $index => $item) {
            $row = ['index' => $index, 'food_id' => (int) $item['food_id'], 'quantity' => (float) $item['quantity'], 'unit' => UnitResolver::normalize($item['unit'])];
            $food = $foods->get($row['food_id']);

            if ($food === null) {
                $rows[] = $row + ['error' => ['code' => 'food_not_found', 'message' => 'No food with this id is available to this user.']];

                continue;
            }

            $weighedCooked = (bool) ($item['weighed_cooked'] ?? false);

            try {
                $nutrients = $this->log->preview($food, $row['quantity'], $row['unit'], $weighedCooked);
            } catch (UnknownUnitException $e) {
                $rows[] = $row + ['error' => [
                    'code' => 'unknown_unit',
                    'message' => $e->getMessage(),
                    'available_units' => array_column($this->units->unitsFor($food), 'unit'),
                ]];

                continue;
            } catch (InvalidQuantityException $e) {
                $rows[] = $row + ['error' => ['code' => 'invalid_quantity', 'message' => $e->getMessage()]];

                continue;
            } catch (LoggingException $e) {
                $rows[] = $row + ['error' => ['code' => $e->reason, 'message' => $e->getMessage()]];

                continue;
            }

            $parts[] = $nutrients;
            $estimate = $estimate || (bool) $food->is_estimate;

            $rows[] = $row + [
                'name_ar' => $food->name_ar,
                'name_en' => $food->name_en,
                'weighed_cooked' => $weighedCooked,
                'grams_entered' => $this->units->toGrams($food, $row['quantity'], $row['unit']),
                'nutrition' => $this->presenter->nutrients($nutrients),
                'is_estimate' => (bool) $food->is_estimate,
                'confidence' => $food->confidence?->value,
                'source' => $food->source,
            ];
        }

        $total = $this->calculator->dailyTotals($parts);
        $result = [
            'items' => $rows,
            'total' => $this->presenter->nutrients($total),
            'complete' => count($parts) === count($items),
            'is_estimate' => $estimate,
            'saved' => false,
        ];

        if (isset($data['servings'])) {
            $servings = (float) $data['servings'];
            $result['servings'] = $servings;
            $result['per_serving'] = $this->presenter->nutrients($total->scale(1 / $servings));
        }

        return $result;
    }
}
