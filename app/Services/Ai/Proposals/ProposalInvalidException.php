<?php

namespace App\Services\Ai\Proposals;

use App\Services\Ai\Exceptions\AiException;

/**
 * A proposal (or the user's edits to one) cannot be calculated / executed as given.
 * `$errors` is structured for both the model (tool error result) and the UI (field errors).
 */
class ProposalInvalidException extends AiException
{
    /**
     * @param  list<array{field: string, code: string, message: string}>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors[0]['message'] ?? __('coach.proposals.invalid'));
    }

    /** @return array<string, list<string>> field => messages, the shape Livewire's addError loop expects */
    public function messagesByField(): array
    {
        $byField = [];
        foreach ($this->errors as $error) {
            $byField[$error['field']][] = $error['message'];
        }

        return $byField;
    }
}
