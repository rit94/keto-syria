<?php

namespace App\Services\Ai;

use stdClass;

/**
 * One Messages-API response. `content` keeps the blocks as decoded objects so they can be
 * stored and replayed without loss (thinking blocks, empty tool inputs, unknown block types).
 */
final readonly class AiResponse
{
    /**
     * @param  list<stdClass>  $content
     * @param  array<string, int>  $usage  input_tokens, output_tokens, cache_creation_input_tokens, cache_read_input_tokens
     */
    public function __construct(
        public ?string $id,
        public ?string $model,
        public array $content,
        public ?string $stopReason,
        public ?string $refusalCategory,
        public array $usage,
    ) {}

    public static function fromBody(stdClass $body): self
    {
        $content = [];
        foreach (is_array($body->content ?? null) ? $body->content : [] as $block) {
            if ($block instanceof stdClass && is_string($block->type ?? null)) {
                $content[] = $block;
            }
        }

        $usage = [];
        foreach (['input_tokens', 'output_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens'] as $key) {
            $usage[$key] = (int) ($body->usage->{$key} ?? 0);
        }

        return new self(
            id: is_string($body->id ?? null) ? $body->id : null,
            model: is_string($body->model ?? null) ? $body->model : null,
            content: $content,
            stopReason: is_string($body->stop_reason ?? null) ? $body->stop_reason : null,
            refusalCategory: is_string($body->stop_details->category ?? null) ? $body->stop_details->category : null,
            usage: $usage,
        );
    }

    /** All text blocks joined – never read content by position, thinking blocks may come first. */
    public function text(): string
    {
        $parts = [];
        foreach ($this->content as $block) {
            if ($block->type === 'text' && is_string($block->text ?? null)) {
                $parts[] = $block->text;
            }
        }

        return trim(implode("\n\n", $parts));
    }

    /**
     * @return list<array{id: string, name: string, input: array<string, mixed>}>
     */
    public function toolUses(): array
    {
        $uses = [];
        foreach ($this->content as $block) {
            if ($block->type !== 'tool_use' || ! is_string($block->id ?? null) || ! is_string($block->name ?? null)) {
                continue;
            }
            $input = json_decode(json_encode($block->input ?? new stdClass), true);
            $uses[] = ['id' => $block->id, 'name' => $block->name, 'input' => is_array($input) ? $input : []];
        }

        return $uses;
    }

    public function isRefusal(): bool
    {
        return $this->stopReason === 'refusal';
    }

    /** @return array<string, mixed> safe to log / serialise (no credentials are ever part of a response) */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'model' => $this->model,
            'stop_reason' => $this->stopReason,
            'usage' => $this->usage,
        ];
    }
}
