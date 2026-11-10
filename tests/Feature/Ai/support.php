<?php

/*
 * Shared helpers for the AI tests. No network: the Anthropic API is scripted with Http::fake().
 */

use App\Models\Food;
use App\Services\Food\FoodIndexer;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const AI_TEST_KEY = 'sk-ant-test-THIS-IS-NOT-A-REAL-KEY-0123456789';

/** Configure a fake key and make retries instant. */
function aiConfigured(): void
{
    config(['services.anthropic.key' => AI_TEST_KEY]);
    Sleep::fake();
}

/**
 * Script the API: each element is a response body (array) or [body, status] / [body, status, headers].
 *
 * @param  list<array<mixed>>  $responses
 */
function aiFake(array $responses): void
{
    // start from a clean client: earlier stubs and the list of recorded requests are dropped,
    // so aiRequests()[0] is always the first request made after this call
    Http::swap(new Factory(app(Dispatcher::class)));

    $sequence = Http::sequence();

    foreach ($responses as $response) {
        if (array_is_list($response)) {
            $sequence->push($response[0], $response[1] ?? 200, $response[2] ?? []);
        } else {
            $sequence->push($response, 200);
        }
    }

    Http::fake(['api.anthropic.com/*' => $sequence]);
}

/** A final text answer. */
function aiText(string $text, array $overrides = []): array
{
    return array_merge([
        'id' => 'msg_'.uniqid(),
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-final-'.uniqid()],
            ['type' => 'text', 'text' => $text],
        ],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 100, 'output_tokens' => 20, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 50],
    ], $overrides);
}

/**
 * A turn that calls tools. $calls: list of [tool name, input] or [tool name, input, id].
 */
function aiToolUse(array $calls, array $overrides = []): array
{
    $content = [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-tools-'.uniqid()]];

    foreach ($calls as $index => $call) {
        $content[] = [
            'type' => 'tool_use',
            'id' => $call[2] ?? 'toolu_'.uniqid().$index,
            'name' => $call[0],
            // an empty input must travel as a JSON object, like the real API sends it
            'input' => $call[1] === [] ? new stdClass : $call[1],
        ];
    }

    return array_merge([
        'id' => 'msg_'.uniqid(),
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => $content,
        'stop_reason' => 'tool_use',
        'usage' => ['input_tokens' => 200, 'output_tokens' => 40, 'cache_creation_input_tokens' => 10, 'cache_read_input_tokens' => 0],
    ], $overrides);
}

/** A structured-output answer of the food description parser. */
function aiParsed(array $items, ?string $meal = null, array $questions = []): array
{
    $items = array_map(fn (array $item) => $item + [
        'alt_names' => [], 'quantity' => null, 'unit' => null, 'cooking_method' => null, 'state' => 'unknown', 'is_cooking_fat' => false,
    ], $items);

    return aiText(json_encode(['items' => $items, 'meal' => $meal, 'questions' => $questions], JSON_UNESCAPED_UNICODE));
}

/** @return list<array<string, mixed>> decoded JSON bodies of every request sent to the API, in order */
function aiRequests(): array
{
    return Http::recorded()
        ->map(fn (array $pair) => json_decode($pair[0]->body(), true))
        ->values()->all();
}

/** @return list<Request> */
function aiRawRequests(): array
{
    return Http::recorded()->map(fn (array $pair) => $pair[0])->values()->all();
}

/** Every tool_result block of a request body, keyed by tool_use_id, with decoded content. */
function aiToolResults(array $request): array
{
    $results = [];
    foreach ($request['messages'] as $message) {
        foreach ((array) $message['content'] as $block) {
            if (($block['type'] ?? null) === 'tool_result') {
                $results[$block['tool_use_id']] = ['is_error' => $block['is_error'] ?? false, 'data' => json_decode($block['content'], true)];
            }
        }
    }

    return $results;
}

/**
 * A catalogue-style food with aliases and household units, indexed the way the real
 * catalogue seeder does it so FoodSearchService ranks it normally.
 *
 * @param  array<string, mixed>  $attributes
 * @param  list<string>  $aliases
 * @param  list<array{0: string, 1: float, 2?: bool}>  $units  [key, grams, isDefault]
 */
function aiFood(array $attributes, array $aliases = [], array $units = []): Food
{
    $food = Food::factory()->create($attributes);
    $indexer = app(FoodIndexer::class);

    $indexer->sync($food, $aliases);
    $indexer->syncUnits($food, array_map(fn (array $u) => [
        'unit' => $u[0], 'label_ar' => $u[0], 'label_en' => $u[0], 'grams' => $u[1], 'is_default' => $u[2] ?? false,
    ], $units));

    return $food->fresh();
}

/** The foods most AI tests need: egg (piece), raw + grilled chicken breast, olive oil, labneh. */
function aiSeedFoods(): array
{
    $egg = aiFood([
        'name_ar' => 'بيض كامل (ني)', 'name_en' => 'Egg, whole, raw', 'category' => 'eggs', 'state' => 'raw',
        'calories' => 143, 'protein_g' => 12.56, 'carbs_g' => 0.72, 'fiber_g' => 0, 'fat_g' => 9.51, 'saturated_fat_g' => 3.126,
    ], ['بيض', 'بيضة', 'بيضه', 'egg', 'eggs'], [['piece', 50, true]]);

    $chickenRaw = aiFood([
        'name_ar' => 'صدر فروج بدون جلد (ني)', 'name_en' => 'Chicken breast, skinless, raw', 'category' => 'poultry', 'state' => 'raw',
        'cooked_yield' => 0.75,
        'calories' => 120, 'protein_g' => 22.5, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 2.62, 'saturated_fat_g' => 0.563,
    ], ['صدر فروج', 'صدر دجاج', 'chicken breast'], [['piece', 118, true]]);

    $chickenCooked = aiFood([
        'name_ar' => 'صدر فروج مشوي بدون جلد', 'name_en' => 'Chicken breast, skinless, roasted', 'category' => 'poultry', 'state' => 'cooked',
        'parent_food_id' => $chickenRaw->id,
        'calories' => 165, 'protein_g' => 31.02, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 3.57, 'saturated_fat_g' => 1.01,
    ], ['صدر فروج مشوي', 'صدر دجاج مشوي', 'شيش طاووق', 'grilled chicken breast'], [['piece', 86, true]]);

    $oil = aiFood([
        'name_ar' => 'زيت زيتون', 'name_en' => 'Olive oil', 'category' => 'fats', 'state' => 'na',
        'calories' => 884, 'protein_g' => 0, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 100, 'saturated_fat_g' => 13.808,
    ], ['زيت الزيتون', 'زيت', 'olive oil'], [['tbsp', 13.5, true], ['tsp', 4.5]]);

    $labneh = aiFood([
        'name_ar' => 'لبنة', 'name_en' => 'Labneh', 'category' => 'dairy', 'state' => 'na',
        'calories' => 160, 'protein_g' => 7.5, 'carbs_g' => 4.2, 'fiber_g' => null, 'fat_g' => 12.5, 'saturated_fat_g' => null,
        'is_estimate' => true, 'confidence' => 'medium', 'source' => 'Estimate from producer labels',
    ], ['لبنه', 'لبنة بلدية', 'labneh', 'labaneh'], [['tbsp', 15, true]]);

    return compact('egg', 'chickenRaw', 'chickenCooked', 'oil', 'labneh');
}
