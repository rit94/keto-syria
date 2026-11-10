<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\Food;
use App\Models\User;
use App\Services\Ai\Support\FoodPresenter;

class GetFoodTool extends AbstractTool
{
    public function __construct(private readonly FoodPresenter $presenter) {}

    public function name(): string
    {
        return 'get_food';
    }

    public function description(): string
    {
        return 'Full details of one food by id: facts per 100 g, state, units, source notes, and its raw / cooked / drained variants. '
            .'Call it when you need the notes or want to pick the right variant of a food you already found.';
    }

    public function inputSchema(): array
    {
        return self::schema(['food_id' => ['type' => 'integer']], ['food_id']);
    }

    protected function rules(): array
    {
        return ['food_id' => ['required', 'integer']];
    }

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $food = Food::query()->visibleTo($user)->with('units')->find((int) $data['food_id']);

        if ($food === null) {
            return self::error('food_not_found', 'No food with this id is available to this user.');
        }

        $familyRoot = $food->parent_food_id ?? $food->id;
        $variants = Food::query()->visibleTo($user)
            ->where(fn ($q) => $q->where('parent_food_id', $familyRoot)->orWhere('id', $familyRoot))
            ->where('id', '!=', $food->id)
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'state', 'preparation']);

        return $this->presenter->food($food) + [
            'notes_ar' => $food->notes_ar,
            'notes_en' => $food->notes_en,
            'variants' => $variants->map(fn (Food $variant) => [
                'food_id' => $variant->id,
                'name_ar' => $variant->name_ar,
                'name_en' => $variant->name_en,
                'state' => $variant->state?->value,
                'preparation' => $variant->preparation?->value,
            ])->all(),
        ];
    }
}
