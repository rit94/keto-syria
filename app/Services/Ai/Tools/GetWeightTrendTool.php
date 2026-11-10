<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\User;
use App\Services\Profile\WeightService;

class GetWeightTrendTool extends AbstractTool
{
    public function __construct(private readonly WeightService $weights) {}

    public function name(): string
    {
        return 'get_weight_trend';
    }

    public function description(): string
    {
        return 'The user\'s weigh-ins and trend over the last days: start and current weight, change, weekly rate (negative means losing), '
            .'goal weight, kilograms remaining and progress percentage. Call it for any question about weight progress, plateaus or pace.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'days' => ['type' => 'integer', 'description' => 'Window length in days, default 90, at most 730.'],
        ]);
    }

    protected function rules(): array
    {
        return ['days' => ['sometimes', 'integer', 'between:1,730']];
    }

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $days = (int) ($data['days'] ?? 90);
        $trend = $this->weights->trend($user, $days);

        // keep the result compact: the summary figures plus the most recent weigh-ins
        $entries = $trend['entries'];
        $trend['entry_count'] = count($entries);
        $trend['entries'] = array_slice($entries, -30);

        return ['days' => $days] + $trend;
    }
}
