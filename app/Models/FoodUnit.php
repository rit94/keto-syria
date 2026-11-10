<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodUnit extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['grams' => 'float', 'is_default' => 'boolean'];
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function label(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->label_ar : $this->label_en;
    }
}
