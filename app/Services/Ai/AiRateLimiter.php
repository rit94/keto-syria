<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Exceptions\AiRateLimitException;
use Illuminate\Support\Facades\RateLimiter;

/** Per-user request budget for the AI features (limits in config ai.rate_limit.<feature>). */
class AiRateLimiter
{
    /**
     * Count one request for the user, or refuse it.
     *
     * @param  string  $feature  'coach' | 'parser'
     *
     * @throws AiRateLimitException
     */
    public function hit(User $user, string $feature): void
    {
        $windows = [
            'minute' => [(int) config("ai.rate_limit.$feature.per_minute", 0), 60],
            'day' => [(int) config("ai.rate_limit.$feature.per_day", 0), 86400],
        ];

        foreach ($windows as $window => [$max, $decay]) {
            $key = "ai:$feature:$window:{$user->id}";
            if ($max > 0 && RateLimiter::tooManyAttempts($key, $max)) {
                throw new AiRateLimitException(max(1, RateLimiter::availableIn($key)), $window);
            }
        }

        foreach ($windows as $window => [$max, $decay]) {
            if ($max > 0) {
                RateLimiter::hit("ai:$feature:$window:{$user->id}", $decay);
            }
        }
    }
}
