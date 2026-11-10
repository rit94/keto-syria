<?php

use App\Services\Ai\AnthropicClient;
use App\Services\Ai\Exceptions\AiNotConfiguredException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

require_once __DIR__.'/support.php';

beforeEach(function () {
    aiConfigured();
    $this->client = app(AnthropicClient::class);
    $this->payload = ['messages' => [['role' => 'user', 'content' => 'hi']]];
});

it('throws AiNotConfiguredException and sends nothing when the key is missing', function (mixed $key) {
    config(['services.anthropic.key' => $key]);
    Http::fake();

    expect($this->client->isConfigured())->toBeFalse()
        ->and(fn () => $this->client->messages($this->payload))->toThrow(AiNotConfiguredException::class);

    Http::assertNothingSent();
})->with([null, '', '   ']);

it('posts to the messages endpoint with the key only in the header, and the configured defaults', function () {
    aiFake([aiText('مرحبا')]);

    $response = $this->client->messages($this->payload);

    expect($response->text())->toBe('مرحبا')
        ->and($response->stopReason)->toBe('end_turn')
        ->and($response->usage['cache_read_input_tokens'])->toBe(50);

    $request = aiRawRequests()[0];
    $body = json_decode($request->body(), true);

    expect($request->url())->toBe('https://api.anthropic.com/v1/messages')
        ->and($request->method())->toBe('POST')
        ->and($request->header('x-api-key'))->toBe([AI_TEST_KEY])
        ->and($request->header('anthropic-version'))->toBe(['2026-10-21'])
        ->and($request->header('anthropic-beta'))->toBe(['server-side-fallback-2026-07-01'])
        ->and($body['model'])->toBe('claude-opus-5-5')
        ->and($body['max_tokens'])->toBe(16000)
        ->and($body['fallbacks'])->toBe('default')
        ->and($request->body())->not->toContain(AI_TEST_KEY)
        // parameters the current models reject must never be sent
        ->and($body)->not->toHaveKeys(['temperature', 'top_p', 'top_k', 'thinking', 'tool_choice']);
});

it('leaves the refusal fallback out when it is disabled', function () {
    config(['ai.refusal_fallback' => false, 'ai.model' => 'claude-haiku-5-5']);
    aiFake([aiText('ok')]);

    $this->client->messages($this->payload);

    $request = aiRawRequests()[0];

    expect($request->hasHeader('anthropic-beta'))->toBeFalse()
        ->and(json_decode($request->body(), true))->not->toHaveKey('fallbacks')
        ->and(json_decode($request->body(), true)['model'])->toBe('claude-haiku-5-5');
});

it('retries a 429 with backoff and then succeeds', function () {
    aiFake([
        [['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'slow down']], 429, ['retry-after' => '2']],
        aiText('تمام'),
    ]);

    $response = $this->client->messages($this->payload);

    expect($response->text())->toBe('تمام');
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
    // retry-after (2 s) wins over the 500 ms base delay
    Sleep::assertSequence([Sleep::for(2000)->milliseconds()]);
});

it('retries overloaded and server errors, then gives up with AiUnavailableException', function (int $status, string $type, string $reason) {
    aiFake(array_fill(0, 3, [['type' => 'error', 'error' => ['type' => $type, 'message' => 'nope']], $status]));

    try {
        $this->client->messages($this->payload);
        $this->fail('expected AiUnavailableException');
    } catch (AiUnavailableException $e) {
        expect($e->status)->toBe($status)
            ->and($e->errorType)->toBe($type)
            ->and($e->reason)->toBe($reason)
            ->and($e->getMessage())->not->toContain(AI_TEST_KEY);
    }

    // first attempt + ai.retries.max (2)
    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
})->with([
    [529, 'overloaded_error', AiUnavailableException::OVERLOADED],
    [500, 'api_error', AiUnavailableException::HTTP],
    [429, 'rate_limit_error', AiUnavailableException::OVERLOADED],
]);

it('does not retry a client error', function () {
    aiFake([[['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'bad']], 400]]);

    expect(fn () => $this->client->messages($this->payload))->toThrow(AiUnavailableException::class);

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('retries connection failures and reports them without leaking details', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out for https://api.anthropic.com/v1/messages'));

    try {
        $this->client->messages($this->payload);
        $this->fail('expected AiUnavailableException');
    } catch (AiUnavailableException $e) {
        expect($e->reason)->toBe(AiUnavailableException::CONNECTION)
            ->and($e->getPrevious())->toBeNull()
            ->and($e->getMessage())->toBe(__('coach.errors.unavailable'));
    }

    Sleep::assertSleptTimes(2);
});

it('treats an unreadable success body as unavailable', function () {
    Http::fake(['api.anthropic.com/*' => Http::response('<html>gateway</html>', 200)]);

    expect(fn () => $this->client->messages($this->payload))
        ->toThrow(fn (AiUnavailableException $e) => expect($e->reason)->toBe(AiUnavailableException::BAD_RESPONSE));
});

it('never writes the API key to the log', function () {
    $logged = [];
    Log::listen(function ($event) use (&$logged) {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    aiFake(array_fill(0, 3, [['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'boom']], 500]));

    try {
        $this->client->messages($this->payload);
    } catch (AiUnavailableException) {
    }

    expect($logged)->not->toBeEmpty()
        ->and(implode("\n", $logged))->not->toContain(AI_TEST_KEY);
});

it('keeps the key off the client object and out of every serialised form', function () {
    aiFake([aiText('ok')]);
    $response = $this->client->messages($this->payload);

    $dump = print_r($this->client, true).serialize($response).json_encode($response->toArray()).var_export($response, true);

    expect($dump)->not->toContain(AI_TEST_KEY);
});
