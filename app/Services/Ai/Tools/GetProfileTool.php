<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\User;

class GetProfileTool extends AbstractTool
{
    public function name(): string
    {
        return 'get_profile';
    }

    public function description(): string
    {
        return 'The user\'s body data, goal, keto approach, daily calorie and macro targets, and whether they need medical supervision. '
            .'Call it when advice depends on who the user is (targets, goal, pace of weight change, fasting, supplements, any health-related question).';
    }

    public function inputSchema(): array
    {
        return self::schema([]);
    }

    protected function rules(): array
    {
        return [];
    }

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $profile = $user->profile()->first();

        if ($profile === null) {
            return ['has_profile' => false, 'message' => 'The user has not completed their profile yet, so there are no targets. Suggest completing it in the app.'];
        }

        return [
            'has_profile' => true,
            'sex' => $profile->sex?->value,
            'age_years' => $profile->birth_date === null ? null : $profile->age(),
            'height_cm' => $profile->height_cm,
            'weight_kg' => $profile->weight_kg,
            'goal_weight_kg' => $profile->goal_weight_kg,
            'activity_level' => $profile->activity_level?->value,
            'goal' => $profile->goal?->value,
            'approach' => $profile->approach?->value,
            'targets' => $profile->targets()->toArray(),
            'targets_customized_by_user' => (bool) $profile->targets_customized,
            'needs_medical_supervision' => (bool) $profile->needs_medical_supervision,
        ];
    }
}
