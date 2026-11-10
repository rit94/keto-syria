<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stored message of a coach conversation.
 *
 * `content` is the list of Messages-API content blocks exactly as sent / received (text,
 * image reference, thinking, tool_use, tool_result ...), kept as a JSON string so it can be
 * replayed without any change. For rendering a chat use isVisible() + displayText().
 *
 * role:  user | assistant | note
 * meta.kind:
 *   user          a message typed by the user (meta.text = what they typed, meta.image = stored path)
 *   tool_results  tool output fed back to the model (role user, hidden from the chat)
 *   assistant     a model reply (hidden while it only carries tool calls)
 *   synthetic     an app-written assistant message (iteration limit, cut-off reply, refusal)
 *   proposal_note an app-generated fact about a proposal's outcome (role note, hidden)
 */
class CoachMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_NOTE = 'note';

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CoachConversation::class, 'conversation_id');
    }

    /** Content blocks as associative arrays (read) – accepts a list of arrays / objects (write). */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? [] : (json_decode($value, true) ?? []),
            set: fn (array|string $value) => is_string($value) ? $value : json_encode(array_values($value), self::JSON_FLAGS),
        );
    }

    /**
     * Content blocks decoded as objects, so empty JSON objects stay objects when re-encoded.
     *
     * @return list<\stdClass>
     */
    public function rawBlocks(): array
    {
        $decoded = json_decode($this->attributes['content'] ?? '[]', false);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_object')) : [];
    }

    public function kind(): string
    {
        return (string) ($this->meta['kind'] ?? $this->role);
    }

    /** Should this message appear as a bubble in the chat? */
    public function isVisible(): bool
    {
        return match ($this->kind()) {
            'user', 'synthetic' => true,
            'assistant' => $this->displayText() !== '',
            default => false,
        };
    }

    /** What to show for this message: the user's own words, or the assistant's text blocks. */
    public function displayText(): string
    {
        if ($this->kind() === 'user') {
            return (string) ($this->meta['text'] ?? '');
        }

        $parts = [];
        foreach ($this->content as $block) {
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $parts[] = $block['text'];
            }
        }

        return trim(implode("\n\n", $parts));
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('role', [self::ROLE_USER, self::ROLE_ASSISTANT]);
    }
}
