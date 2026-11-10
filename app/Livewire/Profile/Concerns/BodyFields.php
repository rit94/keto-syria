<?php

namespace App\Livewire\Profile\Concerns;

use App\Models\Profile;

/**
 * Body data + goal form state shared by onboarding and the profile page.
 *
 * Property names match the keys ProfileService expects, so its validation errors
 * land on the right inputs without any mapping.
 */
trait BodyFields
{
    public string $sex = '';

    public string $birth_date = '';

    /** @var string|int|float|null */
    public $height_cm = null;

    /** @var string|int|float|null */
    public $weight_kg = null;

    /** @var string|int|float|null */
    public $goal_weight_kg = null;

    public string $activity_level = '';

    public string $goal = '';

    public string $approach = '';

    /** @return list<string> */
    public static function bodyFieldNames(): array
    {
        return ['sex', 'birth_date', 'height_cm', 'weight_kg', 'goal_weight_kg', 'activity_level', 'goal', 'approach'];
    }

    /**
     * The $data array for ProfileService::preview() / save().
     *
     * @return array<string, mixed>
     */
    protected function bodyData(): array
    {
        return [
            'sex' => $this->sex,
            'birth_date' => $this->birth_date,
            'height_cm' => $this->blankToNull($this->height_cm),
            'weight_kg' => $this->blankToNull($this->weight_kg),
            'goal_weight_kg' => $this->blankToNull($this->goal_weight_kg),
            'activity_level' => $this->activity_level,
            'goal' => $this->goal,
            'approach' => $this->approach,
        ];
    }

    protected function fillBodyFields(Profile $profile): void
    {
        $this->sex = $profile->sex->value;
        $this->birth_date = $profile->birth_date->toDateString();
        $this->height_cm = $profile->height_cm;
        $this->weight_kg = $profile->weight_kg;
        $this->goal_weight_kg = $profile->goal_weight_kg;
        $this->activity_level = $profile->activity_level->value;
        $this->goal = $profile->goal->value;
        $this->approach = $profile->approach->value;
    }

    /**
     * The same $data array built from what is stored (used when only targets change).
     *
     * @return array<string, mixed>
     */
    protected function bodyDataFrom(Profile $profile): array
    {
        return [
            'sex' => $profile->sex->value,
            'birth_date' => $profile->birth_date->toDateString(),
            'height_cm' => $profile->height_cm,
            'weight_kg' => $profile->weight_kg,
            'goal_weight_kg' => $profile->goal_weight_kg,
            'activity_level' => $profile->activity_level->value,
            'goal' => $profile->goal->value,
            'approach' => $profile->approach->value,
            'needs_medical_supervision' => $profile->needs_medical_supervision,
        ];
    }

    protected function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
