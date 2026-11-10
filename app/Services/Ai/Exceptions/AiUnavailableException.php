<?php

namespace App\Services\Ai\Exceptions;

use Throwable;

/**
 * The model could not be reached or did not give a usable answer: network failure, HTTP error
 * (after retries for the retryable ones), a safety refusal, or a malformed response.
 *
 * `$reason` is a stable machine key; `$status` / `$errorType` come from the API when it answered.
 */
class AiUnavailableException extends AiException
{
    public const CONNECTION = 'connection';

    public const HTTP = 'http';

    public const OVERLOADED = 'overloaded';

    public const REFUSAL = 'refusal';

    public const BAD_RESPONSE = 'bad_response';

    public function __construct(
        public readonly string $reason = self::HTTP,
        public readonly ?int $status = null,
        public readonly ?string $errorType = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(__(match ($reason) {
            self::REFUSAL => 'coach.errors.refused',
            self::OVERLOADED => 'coach.errors.overloaded',
            default => 'coach.errors.unavailable',
        }), 0, $previous);
    }
}
