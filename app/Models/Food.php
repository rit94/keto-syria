<?php

namespace App\Models;

use App\Domain\Nutrition\Nutrients;
use App\Enums\CarbBasis;
use App\Enums\Confidence;
use App\Enums\FoodCategory;
use App\Enums\FoodState;
use App\Enums\Preparation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Food extends Model
{
    use HasFactory;

    protected $table = 'foods';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'category' => FoodCategory::class,
            'state' => FoodState::class,
            'preparation' => Preparation::class,
            'label_basis' => CarbBasis::class,
            'confidence' => Confidence::class,
            'calories' => 'float',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'fiber_g' => 'float',
            'fat_g' => 'float',
            'saturated_fat_g' => 'float',
            'cooked_yield' => 'float',
            'extra_nutrients' => 'array',
            'is_estimate' => 'boolean',
            'is_keto_friendly' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_food_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_food_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(FoodAlias::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(FoodUnit::class);
    }

    /** Catalogue foods plus the given user's own custom foods. Never another user's. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('foods.user_id')
            ->when($user, fn (Builder $q) => $q->orWhere('foods.user_id', $user->id)));
    }

    public function isCustom(): bool
    {
        return $this->user_id !== null;
    }

    /** Facts per 100 g as an engine value object. The only bridge from the table to the engine. */
    public function per100g(): Nutrients
    {
        return new Nutrients(
            calories: $this->calories,
            protein: $this->protein_g,
            carbs: $this->carbs_g,
            fiber: $this->fiber_g,
            fat: $this->fat_g,
            saturatedFat: $this->saturated_fat_g,
            extras: array_map('floatval', $this->extra_nutrients ?? []),
        );
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar' ? $this->name_ar : ($this->name_en ?: $this->name_ar);
    }
}
