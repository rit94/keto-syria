<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\AiNotConfiguredException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use stdClass;

/**
 * Thin server-side wrapper around POST /v1/messages (Anthropic Messages API).
 *
 * - The key is read from config at call time and only ever placed in the x-api-key header.
 *   It is not kept on the object, logged, or included in any exception.
 * - Retries 408/409/429/5xx/529 and connection failures with exponential backoff
 *   (honouring `retry-after`), then throws AiUnavailableException.
 * - Uses Laravel's Http client, so tests script the API with Http::fake().
 *
 * Callers build the request body (system, tools, messages, output_config ...); this class
 * adds the model default, max_tokens default and the refusal-fallback opt-in.
 */
class AnthropicClient
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private const RETRYABLE = [408, 409, 429, 529];

    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    /** @throws AiNotConfiguredException */
    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new AiNotConfiguredException;
        }
    }

    /**
     * @param  array<string, mixed>  $payload  Messages API body; `model` and `max_tokens` default from config/ai.php
     *
     * @throws AiNotConfiguredException no API key
     * @throws AiUnavailableException network / HTTP failure after retries, or an unreadable response
     */
    public function messages(array $payload): AiResponse
    {
        $this->assertConfigured();

        $payload['model'] ??= (string) config('ai.model');
        $payload['max_tokens'] ??= (int) config('ai.coach.max_tokens', 16000);

        $headers = [
            'x-api-key' => $this->key(),
            'anthropic-version' => (string) config('ai.version', '2026-10-21'),
        ];

        if (config('ai.refusal_fallback')) {
            $headers['anthropic-beta'] = self::FALLBACK_BETA;
            $payload['fallbacks'] ??= 'default';
        }

        $url = rtrim((string) config('ai.base_url'), '/').'/v1/messages';
        $maxRetries = max(0, (int) config('ai.retries.max', 2));

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = Http::withHeaders($headers)
                    ->acceptJson()
                    ->asJson()
                    ->connectTimeout((int) config('ai.connect_timeout', 10))
                    ->timeout((int) config('ai.timeout', 120))
                    ->post($url, $payload);
            } catch (ConnectionException $e) {
                if ($attempt < $maxRetries) {
                    $this->backoff($attempt, null);

                    continue;
                }

                Log::warning('ai.request_failed', ['reason' => 'connection', 'attempts' => $attempt + 1]);

                // the original exception is deliberately not chained: its message can carry the full URL
                throw new AiUnavailableException(AiUnavailableException::CONNECTION);
            }

            if ($response->successful()) {
                return $this->parse($response);
            }

            $status = $response->status();
            $retryable = in_array($status, self::RETRYABLE, true) || $status >= 500;

            if ($retryable && $attempt < $maxRetries) {
                $this->backoff($attempt, $response->header('retry-after'));

                continue;
            }

            $errorType = $response->json('error.type');
            $errorType = is_string($errorType) ? $errorType : null;

            Log::warning('ai.request_failed', [
                'status' => $status,
                'type' => $errorType,
                'message' => mb_substr((string) $response->json('error.message'), 0, 500),
                'request_id' => $response->header('request-id'),
                'attempts' => $attempt + 1,
            ]);

            throw new AiUnavailableException(
                $status === 529 || $status === 429 ? AiUnavailableException::OVERLOADED : AiUnavailableException::HTTP,
                $status,
                $errorType,
            );
        }
    }

    private function parse(Response $response): AiResponse
    {
        $body = json_decode($response->body(), false);

        if (! $body instanceof stdClass || ! is_array($body->content ?? null)) {
            Log::warning('ai.request_failed', ['reason' => 'bad_response', 'status' => $response->status()]);

            throw new AiUnavailableException(AiUnavailableException::BAD_RESPONSE, $response->status());
        }

        return AiResponse::fromBody($body);
    }

    private function backoff(int $attempt, ?string $retryAfter): void
    {
        $base = max(1, (int) config('ai.retries.base_delay_ms', 500));
        $max = max($base, (int) config('ai.retries.max_delay_ms', 8000));

        $delay = min($max, $base * (2 ** $attempt));
        if ($retryAfter !== null && is_numeric($retryAfter)) {
            $delay = min($max, max($delay, (int) ceil((float) $retryAfter * 1000)));
        }

        Sleep::for($delay)->milliseconds();
    }

    private function key(): string
    {
        $key = config('services.anthropic.key');

        return is_string($key) ? trim($key) : '';
    }
}
