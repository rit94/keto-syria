<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\User;
use App\Services\Ai\Support\FoodPresenter;
use App\Services\Food\FoodSearchService;

class SearchFoodsTool extends AbstractTool
{
    public function __construct(
        private readonly FoodSearchService $search,
        private readonly FoodPresenter $presenter,
    ) {}

    public function name(): string
    {
        return 'search_foods';
    }

    public function description(): string
    {
        return 'Find foods in the database (shared Syrian catalogue plus this user\'s own custom foods) by Arabic, Syrian-dialect or English name. '
            .'Call this before any calculation or proposal to get food ids; never invent an id. Returns the best matches first, each with a match score '
            .'from zero to one, names, facts per 100 g, raw/cooked state, drained/undrained preparation, usable units with their gram weights, '
            .'the data source, confidence and whether the values are an estimate. Search one food per call, with a short name (not a whole sentence). '
            .'If nothing fits, say so; use request_custom_food rather than guessing values.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'query' => ['type' => 'string', 'description' => 'Food name as the user said it, e.g. "صدر فروج مشوي" or "labneh".'],
            'limit' => ['type' => 'integer', 'description' => 'Maximum results, default 5, at most 10.'],
        ], ['query']);
    }

    protected function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:1', 'max:120'],
            'limit' => ['sometimes', 'integer', 'between:1,10'],
        ];
    }

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $matches = $this->search->match($data['query'], $user, (int) ($data['limit'] ?? 5));

        return [
            'query' => $data['query'],
            'count' => $matches->count(),
            'results' => $matches->map(fn (array $match) => $this->presenter->food($match['food'], (float) $match['score']))->all(),
        ];
    }
}
