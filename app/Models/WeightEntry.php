<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['measured_on' => 'date', 'weight_kg' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
