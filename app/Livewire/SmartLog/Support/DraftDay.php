<?php

namespace App\Livewire\SmartLog\Support;

use App\Domain\Nutrition\Nutrients;
use App\Domain\Nutrition\NutritionCalculator;
use App\Models\User;
use App\Services\Ai\SmartLog\FoodDraft;
use App\Services\Ai\Support\AiClock;
use App\Services\Logging\DailySummaryService;

/**
 * "What is left of the day after this meal" for a draft. Every number comes from the
 * nutrition engine: the day's logged totals (DailySummaryService), the draft's totals (computed
 * by FoodDraftService) and NutritionCalculator::remaining().
 */
final class DraftDay
{
    /**
     * @return array{remaining: ?array<string, mixed>, has_targets: bool}
     */
    public static function remainingAfter(User $user, FoodDraft $draft): array
    {
        $summary = app(DailySummaryService::class)->forDate($user, AiClock::parseDate($draft->date) ?? AiClock::today());

        if ($summary->targets === null || $draft->totals === null) {
            return ['remaining' => null, 'has_targets' => $summary->targets !== null];
        }

        $after = $summary->totals->plus(self::nutrients($draft->totals));

        return [
            'remaining' => app(NutritionCalculator::class)->remaining($summary->targets, $after)->toArray(),
            'has_targets' => true,
        ];
    }

    /** Rebuild the engine value object from Nutrients::toArray() output (lossless). */
    private static function nutrients(array $data): Nutrients
    {
        return new Nutrients(
            calories: (float) ($data['calories'] ?? 0),
            protein: (float) ($data['protein_g'] ?? 0),
            carbs: (float) ($data['carbs_g'] ?? 0),
            fiber: isset($data['fiber_g']) ? (float) $data['fiber_g'] : null,
            fat: (float) ($data['fat_g'] ?? 0),
            saturatedFat: isset($data['saturated_fat_g']) ? (float) $data['saturated_fat_g'] : null,
            missing: array_values(array_filter((array) ($data['missing'] ?? []), 'is_string')),
        );
    }
}
