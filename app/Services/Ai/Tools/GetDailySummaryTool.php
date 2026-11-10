<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\User;
use App\Services\Ai\Support\AiClock;
use App\Services\Ai\Support\FoodPresenter;
use App\Services\Logging\DailySummaryService;

class GetDailySummaryTool extends AbstractTool
{
    public function __construct(
        private readonly DailySummaryService $summaries,
        private readonly FoodPresenter $presenter,
    ) {}

    public function name(): string
    {
        return 'get_daily_summary';
    }

    public function description(): string
    {
        return 'The user\'s food diary for one day: every logged entry grouped by meal (with entry ids), totals, daily targets and what remains. '
            .'Call it before suggesting what to eat, when asked what was eaten or how much is left, and to find the entry_id needed to change or delete an entry. '
            .'"remaining" can be negative (target exceeded). When "totals_are_lower_bound" is true some entries lack data, so the real totals may be higher.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, or "today" / "yesterday". Defaults to today (Syria time).'],
        ]);
    }

    protected function rules(): array
    {
        return ['date' => ['sometimes', 'nullable', 'string', 'max:20']];
    }

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $date = AiClock::parseDate($data['date'] ?? null);
        if ($date === null) {
            return self::error('invalid_date', 'Use YYYY-MM-DD, "today" or "yesterday".');
        }

        $summary = $this->summaries->forDate($user, $date);

        $meals = [];
        foreach ($summary->byMeal as $meal => $bucket) {
            if ($bucket['entries']->isEmpty()) {
                continue;
            }
            $meals[$meal] = [
                'entries' => $bucket['entries']->map(fn ($entry) => $this->presenter->entry($entry))->all(),
                'totals' => $this->presenter->nutrients($bucket['totals']),
            ];
        }

        return [
            'date' => $summary->date->toDateString(),
            'entry_count' => $summary->entries->count(),
            'meals' => $meals === [] ? new \stdClass : $meals,
            'totals' => $this->presenter->nutrients($summary->totals),
            'totals_are_lower_bound' => ! $summary->totals->isComplete(),
            'has_estimates' => $summary->entries->contains(fn ($entry) => (bool) $entry->is_estimate),
            'targets' => $summary->targets?->toArray(),
            'remaining' => $summary->remaining?->toArray(),
            'has_profile' => $summary->targets !== null,
        ];
    }
}
