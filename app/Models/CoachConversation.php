<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One chat thread with the Keto Coach.
 *
 * `history_anchor_id` / `thinking_stripped_upto_id` are maintained by
 * App\Services\Ai\Coach\ConversationHistory – UI code never sets them.
 */
class CoachConversation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'history_anchor_id' => 'integer',
            'thinking_stripped_upto_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CoachMessage::class, 'conversation_id')->orderBy('id');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(CoachProposal::class, 'conversation_id');
    }
}
