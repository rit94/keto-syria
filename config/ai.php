<?php

/*
|--------------------------------------------------------------------------
| AI layer (Keto Coach + smart food logging)
|--------------------------------------------------------------------------
|
| The API key itself lives in config/services.php ('services.anthropic.key',
| env ANTHROPIC_API_KEY) and is only ever read server-side by
| App\Services\Ai\AnthropicClient.
|
*/

return [

    // Model ids are used exactly as written (no date suffix).
    'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),

    // Model for the one-shot food description parser; null = same as 'model'.
    'parser_model' => env('ANTHROPIC_PARSER_MODEL'),

    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    'version' => '2026-10-21',

    // Seconds. Thinking is always on for the default model, so replies can take a while.
    'timeout' => (int) env('AI_TIMEOUT', 120),
    'connect_timeout' => (int) env('AI_CONNECT_TIMEOUT', 10),

    // Retries after the first attempt, on 408/409/429/5xx/529 and connection errors.
    'retries' => [
        'max' => (int) env('AI_MAX_RETRIES', 2),
        'base_delay_ms' => 500,
        'max_delay_ms' => 8000,
    ],

    // When a safety classifier declines a request, let the API re-run it on Anthropic's
    // recommended fallback model (beta "server-side-fallback-2026-07-01"). Turn off for
    // models / gateways that reject the `fallbacks` parameter.
    'refusal_fallback' => (bool) env('AI_REFUSAL_FALLBACK', true),

    'coach' => [
        'effort' => env('AI_COACH_EFFORT', 'medium'),
        'max_tokens' => 16000,
        // Maximum model calls per user message (tool-use loop guard).
        'max_iterations' => 8,
        // History is replayed from a stable anchor; once more than `max_history_messages`
        // stored messages would be replayed the anchor moves forward so that roughly
        // `keep_history_messages` remain.
        'max_history_messages' => 80,
        'keep_history_messages' => 40,
        'max_input_chars' => 4000,
    ],

    'parser' => [
        'effort' => env('AI_PARSER_EFFORT', 'low'),
        'max_tokens' => 8000,
        'max_input_chars' => 2000,
        'max_items' => 30,
        // A match scoring below this is "ambiguous" and must be confirmed by the user.
        'resolved_score' => 0.8,
        // Another (unrelated) candidate within this distance of the best score = ambiguous.
        'ambiguity_margin' => 0.03,
        'candidates' => 5,
    ],

    'rate_limit' => [
        'coach' => ['per_minute' => 10, 'per_day' => 200],
        'parser' => ['per_minute' => 15, 'per_day' => 300],
    ],

    // Pending proposals (write actions awaiting the user's confirmation) expire after this.
    'proposal_ttl_minutes' => 30,

    'images' => [
        // Must be a PRIVATE disk – chat images are never publicly reachable.
        'disk' => 'local',
        'directory' => 'coach-images',
        'max_kb' => 4096,
        'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],

    // "Today" for the diary is the user's day in Syria, whatever the server clock says.
    'timezone' => 'Asia/Damascus',

];
