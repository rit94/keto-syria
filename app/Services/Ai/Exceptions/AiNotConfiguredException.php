<?php

namespace App\Services\Ai\Exceptions;

/** No API key is configured (ANTHROPIC_API_KEY). Thrown before anything is sent or stored. */
class AiNotConfiguredException extends AiException
{
    public function __construct()
    {
        parent::__construct(__('coach.errors.not_configured'));
    }
}
