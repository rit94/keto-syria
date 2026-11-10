<?php

namespace App\Models;

use App\Services\Ai\Proposals\ProposalStatus;
use App\Services\Ai\Proposals\ProposalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A write action the coach suggested and the user has not (yet) approved.
 *
 * Nothing in the diary / recipes / plans exists for a proposal until
 * App\Services\Ai\Proposals\ProposalService::confirm() succeeded and `status` is Confirmed.
 *
 * payload  validated input (ids, quantities, units, dates) – what will be executed
 * preview  engine-computed numbers for the confirmation card (see ProposalPreviewBuilder)
 * result   after confirm: ids of the records written, or ['error' => message] when it failed
 */
class CoachProposal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ProposalType::class,
            'status' => ProposalStatus::class,
            'payload' => 'array',
            'preview' => 'array',
            'result' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CoachConversation::class, 'conversation_id');
    }

    public function isPending(): bool
    {
        return $this->status === ProposalStatus::Pending && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->status === ProposalStatus::Expired
            || ($this->status === ProposalStatus::Pending && $this->expires_at !== null && $this->expires_at->isPast());
    }

    /** One-line translated description for the confirmation card. */
    public function summary(): string
    {
        return __('coach.proposals.summary.'.$this->type->value, [
            'count' => $this->preview['item_count'] ?? 1,
            'name' => $this->preview['name'] ?? ($this->preview['before']['description'] ?? $this->preview['entry']['description'] ?? ''),
            'meal' => isset($this->preview['meal']) ? __('enums.meal.'.$this->preview['meal']) : '',
            'date' => $this->preview['date'] ?? ($this->preview['starts_on'] ?? ''),
            'days' => $this->preview['days'] ?? '',
        ]);
    }
}
