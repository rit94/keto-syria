<?php

namespace App\Services\Ai\Exceptions;

/** This user sent too many AI requests (per-minute or per-day limit from config/ai.php). */
class AiRateLimitException extends AiException
{
    public function __construct(public readonly int $retryAfterSeconds, public readonly string $window = 'minute')
    {
        parent::__construct($window === 'day'
            ? __('coach.errors.rate_limited_day')
            : __('coach.errors.rate_limited', ['seconds' => $retryAfterSeconds]));
    }
}
