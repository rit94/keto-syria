<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodAlias extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
