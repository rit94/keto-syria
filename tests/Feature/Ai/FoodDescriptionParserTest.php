<?php

use App\Enums\LogSource;
use App\Enums\Meal;
use App\Models\Food;
use App\Models\FoodLogEntry;
use App\Models\User;
use App\Services\Ai\Exceptions\AiNotConfiguredException;
use App\Services\Ai\Exceptions\AiRateLimitException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\SmartLog\FoodDescriptionParser;
use App\Services\Ai\SmartLog\FoodDraft;
use App\Services\Ai\SmartLog\FoodDraftNotReadyException;
use App\Services\Ai\SmartLog\FoodDraftService;
use App\Services\Logging\FoodLogService;
use Carbon\CarbonImmutable;
use Database\Seeders\FoodCatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/support.php';

beforeEach(function () {
    aiConfigured();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 13:00:00', 'Asia/Damascus'));
    Carbon\Carbon::setTestNow(CarbonImmutable::now());

    $this->parser = app(FoodDescriptionParser::class);
    $this->drafts = app(FoodDraftService::class);
    $this->log = app(FoodLogService::class);
    $this->user = User::factory()->create(['locale' => 'ar']);
    $this->foods = aiSeedFoods();

    $this->item = fn (FoodDraft $draft, string $spoken) => collect($draft->items)->firstWhere('spoken_name', $spoken);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon\Carbon::setTestNow();
});

it('resolves a Syrian-dialect description to database foods with engine nutrition', function () {
    aiFake([aiParsed([
        ['name' => 'بيضتين', 'alt_names' => ['بيض', 'egg'], 'quantity' => 2, 'unit' => 'حبة', 'cooking_method' => 'fried', 'state' => 'cooked'],
        ['name' => 'زيت زيتون', 'alt_names' => ['olive oil'], 'quantity' => 1, 'unit' => 'ملعقة كبيرة', 'is_cooking_fat' => true],
        ['name' => 'صدر فروج مشوي', 'alt_names' => ['صدر دجاج', 'chicken breast'], 'quantity' => 150, 'unit' => 'غرام', 'cooking_method' => 'grilled', 'state' => 'cooked'],
    ], 'breakfast')]);

    $draft = $this->parser->parseText($this->user, 'فطرت بيضتين مقليات بمعلقة زيت زيتون و١٥٠ غرام صدر فروج مشوي');

    $egg = ($this->item)($draft, 'بيضتين');
    $oil = ($this->item)($draft, 'زيت زيتون');
    $chicken = ($this->item)($draft, 'صدر فروج مشوي');

    $eggNutrition = $this->log->preview($this->foods['egg'], 2, 'piece');
    $oilNutrition = $this->log->preview($this->foods['oil'], 1, 'tbsp');
    $chickenNutrition = $this->log->preview($this->foods['chickenCooked'], 150, 'g');

    expect($draft->items)->toHaveCount(3)
        ->and($draft->meal)->toBe('breakfast')
        ->and($draft->mealInferred)->toBeFalse()
        ->and($draft->date)->toBe('2026-11-10')
        ->and($draft->origin)->toBe('text')
        // "بيضتين" itself is unknown – the alternative name "بيض" matched exactly
        ->and($egg['food_id'])->toBe($this->foods['egg']->id)
        ->and($egg['status'])->toBe(FoodDraft::RESOLVED)
        ->and($egg['unit'])->toBe('piece')
        ->and($egg['quantity'])->toBe(2.0)
        ->and($egg['weighed_cooked'])->toBeFalse()
        ->and($egg['nutrition'])->toBe($eggNutrition->toArray())
        ->and($egg['candidates'][0])->toMatchArray(['food_id' => $this->foods['egg']->id, 'score' => 1.0])
        ->and(array_column($egg['units'], 'unit'))->toContain('g', 'piece')
        ->and($oil['food_id'])->toBe($this->foods['oil']->id)
        ->and($oil['unit'])->toBe('tbsp')
        ->and($oil['is_cooking_fat'])->toBeTrue()
        ->and($oil['nutrition'])->toBe($oilNutrition->toArray())
        // the grilled variant is chosen, so the cooked weight is used as it is
        ->and($chicken['food_id'])->toBe($this->foods['chickenCooked']->id)
        ->and($chicken['unit'])->toBe('g')
        ->and($chicken['weighed_cooked'])->toBeFalse()
        ->and($chicken['nutrition'])->toBe($chickenNutrition->toArray())
        ->and($draft->totals)->toBe($eggNutrition->plus($oilNutrition)->plus($chickenNutrition)->toArray())
        ->and($draft->totalsPartial)->toBeFalse()
        ->and($draft->requiresConfirmation)->toBeFalse()
        ->and($draft->isCommittable())->toBeTrue();

    expect(FoodLogEntry::count())->toBe(0);
});

it('asks the model for structure only: strict JSON schema, no nutrition fields, no forced tool', function () {
    aiFake([aiParsed([['name' => 'بيض', 'quantity' => 1, 'unit' => 'piece']])]);

    $this->parser->parseText($this->user, 'بيضة');

    $request = aiRequests()[0];
    $schema = json_encode($request['output_config']['format']['schema']);

    expect($request['output_config']['effort'])->toBe('low')
        ->and($request['output_config']['format']['type'])->toBe('json_schema')
        ->and($request)->not->toHaveKeys(['tool_choice', 'tools', 'thinking', 'temperature'])
        ->and($request['system'][0]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($request['messages'][0]['content'][0]['text'])->toContain('بيضة')
        ->and(array_keys($request['output_config']['format']['schema']['properties']['items']['items']['properties']))
        ->toBe(['name', 'alt_names', 'quantity', 'unit', 'cooking_method', 'state', 'is_cooking_fat']);

    foreach (['calor', 'protein', 'carb', 'fat_g', 'kcal', 'macro'] as $word) {
        expect($schema)->not->toContain($word);
    }

    // every object in the schema is closed, as structured outputs require
    expect(substr_count($schema, '"type":"object"'))->toBe(substr_count($schema, '"additionalProperties":false'));
});

it('ignores nutrition numbers even if the model slips them into its answer', function () {
    aiFake([aiText(json_encode([
        'items' => [[
            'name' => 'بيض', 'alt_names' => [], 'quantity' => 2, 'unit' => 'piece', 'cooking_method' => null, 'state' => 'unknown', 'is_cooking_fat' => false,
            'calories' => 9999, 'nutrition' => ['calories' => 9999, 'protein_g' => 777], 'food_id' => 424242,
        ]],
        'meal' => null, 'questions' => [], 'totals' => ['calories' => 9999],
    ]))]);

    $draft = $this->parser->parseText($this->user, 'بيضتين');

    expect($draft->items[0]['food_id'])->toBe($this->foods['egg']->id)
        ->and($draft->items[0]['nutrition'])->toBe($this->log->preview($this->foods['egg'], 2, 'piece')->toArray())
        ->and(json_encode($draft->toArray()))->not->toContain('9999', '777', '424242');
});

it('flags a missing quantity, an unknown unit and a food it cannot find', function () {
    aiFake([aiParsed([
        ['name' => 'لبنة'],
        ['name' => 'بيض', 'quantity' => 1, 'unit' => 'كاسة'],
        ['name' => 'شنكليش', 'alt_names' => ['shanklish'], 'quantity' => 30, 'unit' => 'g'],
        ['name' => 'زيت زيتون', 'quantity' => 2, 'unit' => 'tbsp', 'is_cooking_fat' => true],
    ], null, ['قديش كمية اللبنة؟'])]);

    $draft = $this->parser->parseText($this->user, 'لبنة وبيض كاسة وشنكليش مع معلقتين زيت');

    $labneh = ($this->item)($draft, 'لبنة');
    $egg = ($this->item)($draft, 'بيض');
    $shanklish = ($this->item)($draft, 'شنكليش');

    expect($labneh['status'])->toBe(FoodDraft::NEEDS_QUANTITY)
        ->and($labneh['food_id'])->toBe($this->foods['labneh']->id)
        ->and($labneh['issues'])->toContain('missing_quantity', 'estimated_values')
        ->and($labneh['nutrition'])->toBeNull()
        ->and($labneh['is_estimate'])->toBeTrue()
        // eggs have no "cup" unit: unknown units are never guessed
        ->and($egg['status'])->toBe(FoodDraft::NEEDS_QUANTITY)
        ->and($egg['unit'])->toBe('cup')
        ->and($egg['issues'])->toContain('unknown_unit')
        ->and($egg['nutrition'])->toBeNull()
        ->and($shanklish['status'])->toBe(FoodDraft::UNRESOLVED)
        ->and($shanklish['food_id'])->toBeNull()
        ->and($shanklish['issues'])->toBe(['no_match'])
        ->and($draft->questions)->toBe(['قديش كمية اللبنة؟'])
        ->and($draft->requiresConfirmation)->toBeTrue()
        ->and($draft->isCommittable())->toBeFalse()
        // the total covers only what could be calculated, and says so
        ->and($draft->totals)->toBe($this->log->preview($this->foods['oil'], 2, 'tbsp')->toArray())
        ->and($draft->totalsPartial)->toBeTrue()
        // 13:00 in Damascus, no meal said
        ->and($draft->meal)->toBe('lunch')
        ->and($draft->mealInferred)->toBeTrue();
});

it('marks an uncertain match as ambiguous and keeps the candidates', function () {
    // two unrelated foods share the alias "جبنة"
    $white = aiFood(['name_ar' => 'جبنة بيضاء بلدية', 'name_en' => 'White cheese', 'category' => 'cheese', 'state' => 'na'], ['جبنة', 'جبنة بيضا']);
    $yellow = aiFood(['name_ar' => 'جبنة قشقوان', 'name_en' => 'Kashkaval', 'category' => 'cheese', 'state' => 'na'], ['جبنة', 'قشقوان']);

    aiFake([aiParsed([
        ['name' => 'جبنة', 'quantity' => 50, 'unit' => 'g'],
        // a typo-level match scores far below the "resolved" threshold
        ['name' => 'لبنيه مدعبله بزيت', 'quantity' => 30, 'unit' => 'g'],
    ], 'dinner')]);

    $draft = $this->parser->parseText($this->user, 'جبنة ٥٠ غرام');
    $cheese = ($this->item)($draft, 'جبنة');

    expect($cheese['status'])->toBe(FoodDraft::AMBIGUOUS)
        ->and($cheese['issues'])->toContain('ambiguous_match')
        ->and(array_column($cheese['candidates'], 'food_id'))->toContain($white->id, $yellow->id)
        ->and($cheese['food_id'])->toBeIn([$white->id, $yellow->id])
        // a tentative preview is shown for the suggested food
        ->and($cheese['nutrition'])->not->toBeNull()
        ->and($draft->requiresConfirmation)->toBeTrue();

    $fuzzy = ($this->item)($draft, 'لبنيه مدعبله بزيت');
    if ($fuzzy['food_id'] !== null) {
        expect($fuzzy['status'])->toBe(FoodDraft::AMBIGUOUS)
            ->and($fuzzy['candidates'][0]['score'])->toBeLessThan(0.8);
    } else {
        expect($fuzzy['status'])->toBe(FoodDraft::UNRESOLVED);
    }

    // the user picks the right cheese → resolved, recalculated by the engine
    $picked = $this->drafts->recalculate($this->user, $draft->withItem($cheese['key'], ['food_id' => $yellow->id]));
    $cheese = ($this->item)($picked, 'جبنة');

    expect($cheese['status'])->toBe(FoodDraft::RESOLVED)
        ->and($cheese['food_id'])->toBe($yellow->id)
        ->and($cheese['issues'])->not->toContain('ambiguous_match')
        ->and($cheese['nutrition'])->toBe($this->log->preview($yellow->fresh(), 50, 'g')->toArray());

    // or simply confirms the suggestion
    $confirmed = $this->drafts->recalculate($this->user, $draft->confirmFood($cheese['key']));
    expect(($this->item)($confirmed, 'جبنة')['status'])->toBe(FoodDraft::RESOLVED);
});

it('does not call raw / cooked variants of the same food ambiguous', function () {
    aiFake([aiParsed([['name' => 'صدر فروج', 'quantity' => 200, 'unit' => 'g', 'state' => 'raw']])]);

    $draft = $this->parser->parseText($this->user, '٢٠٠ غرام صدر فروج ني');

    expect($draft->items[0]['status'])->toBe(FoodDraft::RESOLVED)
        ->and($draft->items[0]['food_id'])->toBe($this->foods['chickenRaw']->id)
        ->and($draft->items[0]['issues'])->toBe([])
        ->and($draft->items[0]['nutrition'])->toBe($this->log->preview($this->foods['chickenRaw'], 200, 'g')->toArray());
});

it('uses the cooked yield when a cooked weight is given for a food that only exists raw', function () {
    $beef = aiFood([
        'name_ar' => 'لحم عجل هبرة (ني)', 'name_en' => 'Lean veal, raw', 'category' => 'meat', 'state' => 'raw', 'cooked_yield' => 0.70,
        'calories' => 150, 'protein_g' => 21, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 7, 'saturated_fat_g' => 3,
    ], ['لحم عجل', 'هبرة']);
    $lamb = aiFood([
        'name_ar' => 'لحم غنم (ني)', 'name_en' => 'Lamb, raw', 'category' => 'meat', 'state' => 'raw', 'cooked_yield' => null,
    ], ['لحم غنم', 'خاروف']);

    aiFake([aiParsed([
        ['name' => 'لحم عجل', 'quantity' => 140, 'unit' => 'g', 'cooking_method' => 'grilled', 'state' => 'cooked'],
        ['name' => 'لحم غنم', 'quantity' => 100, 'unit' => 'g', 'state' => 'cooked'],
        ['name' => 'هبرة', 'quantity' => 100, 'unit' => 'g', 'state' => 'unknown'],
    ])]);

    $draft = $this->parser->parseText($this->user, '١٤٠ غرام لحم عجل مشوي و١٠٠ غرام لحم غنم مطبوخ و١٠٠ هبرة');

    $veal = ($this->item)($draft, 'لحم عجل');
    $lambItem = ($this->item)($draft, 'لحم غنم');
    $unknown = ($this->item)($draft, 'هبرة');

    // 140 g cooked / 0.70 = 200 g raw
    expect($veal['weighed_cooked'])->toBeTrue()
        ->and($veal['nutrition'])->toEqual($this->log->preview($beef, 200, 'g')->toArray())
        ->and($veal['status'])->toBe(FoodDraft::RESOLVED)
        // no yield: never guessed – calculated as raw and flagged for the user
        ->and($lambItem['weighed_cooked'])->toBeFalse()
        ->and($lambItem['issues'])->toContain('cooked_weight_unconvertible')
        ->and($lambItem['nutrition'])->toBe($this->log->preview($lamb, 100, 'g')->toArray())
        // not said whether raw or cooked, and it matters for this food
        ->and($unknown['issues'])->toContain('raw_or_cooked_unknown')
        ->and($unknown['weighed_cooked'])->toBeFalse()
        ->and($draft->requiresConfirmation)->toBeTrue();

    // the user answers "it was the cooked weight"
    $answered = $this->drafts->recalculate($this->user, $draft->withItem($unknown['key'], ['weighed_cooked' => true]));
    $unknown = ($this->item)($answered, 'هبرة');

    expect($unknown['weighed_cooked'])->toBeTrue()
        ->and($unknown['issues'])->not->toContain('raw_or_cooked_unknown')
        ->and($unknown['nutrition'])->toEqual($this->log->preview($beef, 100 / 0.70, 'g')->toArray());
});

it('always requires confirmation for a photo and marks quantities as visual estimates', function () {
    Storage::fake('local');
    aiFake([aiParsed([
        ['name' => 'صدر فروج مشوي', 'alt_names' => ['grilled chicken breast'], 'quantity' => 180, 'unit' => 'g', 'state' => 'cooked'],
        ['name' => 'بيض', 'quantity' => 1, 'unit' => 'piece'],
    ])]);

    $image = UploadedFile::fake()->image('plate.jpg', 30, 30);
    $bytes = file_get_contents($image->getRealPath());

    $draft = $this->parser->parseImage($this->user, $image, 'غداي اليوم', Meal::Lunch);

    expect($draft->origin)->toBe(FoodDraft::ORIGIN_IMAGE)
        ->and($draft->requiresConfirmation)->toBeTrue()
        ->and($draft->meal)->toBe('lunch')
        ->and($draft->items)->toHaveCount(2);

    foreach ($draft->items as $item) {
        expect($item['status'])->toBe(FoodDraft::RESOLVED)
            ->and($item['issues'])->toContain('visual_estimate');
    }

    // the image went to the model inline and was not stored anywhere
    $content = aiRequests()[0]['messages'][0]['content'];
    expect($content[0]['type'])->toBe('image')
        ->and($content[0]['source'])->toBe(['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode($bytes)])
        ->and($content[1]['text'])->toContain('غداي اليوم')
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();

    // still flagged after a recalculation, until the user corrects the amount
    $again = $this->drafts->recalculate($this->user, FoodDraft::fromArray($draft->toArray()));
    expect($again->requiresConfirmation)->toBeTrue();

    $key = $again->items[0]['key'];
    $edited = $this->drafts->recalculate($this->user, $again->withItem($key, ['quantity' => 200]));

    expect($edited->items[0]['issues'])->not->toContain('visual_estimate')
        ->and($edited->items[0]['nutrition'])->toBe($this->log->preview($this->foods['chickenCooked'], 200, 'g')->toArray())
        ->and($edited->items[1]['issues'])->toContain('visual_estimate')
        ->and($edited->requiresConfirmation)->toBeTrue();
});

it('recalculates after edits and survives a round trip through plain array state', function () {
    aiFake([aiParsed([['name' => 'بيض', 'quantity' => 2, 'unit' => 'piece'], ['name' => 'لبنة']], 'breakfast')]);

    $draft = $this->parser->parseText($this->user, 'بيضتين ولبنة');

    // what a Livewire component would hold
    $state = json_decode(json_encode($draft->toArray()), true);
    $restored = FoodDraft::fromArray($state);

    expect($restored->toArray())->toEqual($draft->toArray());

    $labnehKey = ($this->item)($restored, 'لبنة')['key'];
    $eggKey = ($this->item)($restored, 'بيض')['key'];

    $edited = $restored
        ->withItem($labnehKey, ['quantity' => 2, 'unit' => 'tbsp'])
        ->withItem($eggKey, ['quantity' => 3])
        ->withAddedItem($this->foods['oil']->id, 5, 'g')
        ->withMeal(Meal::Dinner)
        ->withDate('2026-11-09');

    $recalculated = $this->drafts->recalculate($this->user, $edited);

    $egg = $this->log->preview($this->foods['egg'], 3, 'piece');
    $labneh = $this->log->preview($this->foods['labneh'], 2, 'tbsp');
    $oil = $this->log->preview($this->foods['oil'], 5, 'g');

    expect(array_column($recalculated->items, 'status'))->toBe([FoodDraft::RESOLVED, FoodDraft::RESOLVED, FoodDraft::RESOLVED])
        ->and($recalculated->items[2]['food_name'])->toBe('زيت زيتون')
        ->and($recalculated->totals)->toBe($egg->plus($labneh)->plus($oil)->toArray())
        ->and($recalculated->totals['missing'])->toBe(['fiber', 'saturated_fat'])
        ->and($recalculated->meal)->toBe('dinner')
        ->and($recalculated->date)->toBe('2026-11-09')
        ->and($recalculated->isCommittable())->toBeTrue()
        ->and($this->drafts->recalculate($this->user, $recalculated->withoutItem($eggKey))->items)->toHaveCount(2);
});

it('recomputes everything from the database when client state was tampered with', function () {
    $stranger = User::factory()->create();
    $private = aiFood(['user_id' => $stranger->id, 'name_ar' => 'وصفة جارنا', 'name_en' => 'Neighbour food']);

    aiFake([aiParsed([['name' => 'بيض', 'quantity' => 2, 'unit' => 'piece']], 'lunch')]);
    $state = $this->parser->parseText($this->user, 'بيضتين')->toArray();

    $state['items'][0]['nutrition'] = ['calories' => 1.0, 'protein_g' => 500.0];
    $state['items'][0]['status'] = FoodDraft::RESOLVED;
    $state['totals'] = ['calories' => 1.0];
    $state['requires_confirmation'] = false;
    $state['items'][] = ['key' => 'x', 'food_id' => $private->id, 'quantity' => 10, 'unit' => 'g', 'status' => FoodDraft::RESOLVED, 'nutrition' => ['calories' => 5.0]];
    $state['items'][] = 'garbage';
    $state['meal'] = 'brunch';
    $state['unknown'] = ['api_key' => 'x'];

    $draft = $this->drafts->recalculate($this->user, FoodDraft::fromArray($state));
    $engine = $this->log->preview($this->foods['egg'], 2, 'piece')->toArray();

    expect($draft->items)->toHaveCount(2)
        ->and($draft->items[0]['nutrition'])->toBe($engine)
        ->and($draft->totals)->toBe($engine)
        // another user's private food is not reachable through a forged id
        ->and($draft->items[1]['status'])->toBe(FoodDraft::UNRESOLVED)
        ->and($draft->items[1]['food_id'])->toBeNull()
        ->and($draft->items[1]['issues'])->toBe(['food_not_accessible'])
        ->and($draft->meal)->toBeNull()
        ->and($draft->toArray())->not->toHaveKey('unknown')
        ->and($draft->isCommittable())->toBeFalse();

    expect(fn () => $this->drafts->commit($this->user, $draft, LogSource::Text))->toThrow(FoodDraftNotReadyException::class);
    expect(FoodLogEntry::count())->toBe(0);
});

it('refuses to commit while any item is unresolved or lacks a quantity', function () {
    aiFake([aiParsed([
        ['name' => 'بيض', 'quantity' => 2, 'unit' => 'piece'],
        ['name' => 'لبنة'],
        ['name' => 'شنكليش', 'quantity' => 30, 'unit' => 'g'],
    ], 'breakfast')]);

    $draft = $this->parser->parseText($this->user, 'بيضتين ولبنة وشنكليش');

    try {
        $this->drafts->commit($this->user, $draft, LogSource::Text);
        $this->fail('expected FoodDraftNotReadyException');
    } catch (FoodDraftNotReadyException $e) {
        expect($e->reason)->toBe(FoodDraftNotReadyException::UNRESOLVED_ITEMS)
            ->and($e->itemKeys)->toHaveCount(2)
            ->and($e->draft->items)->toHaveCount(3)
            ->and($e->getMessage())->toBe(__('coach.draft.errors.unresolved_items'));
    }

    expect(FoodLogEntry::count())->toBe(0);

    // an empty draft is refused too
    aiFake([aiParsed([])]);
    $empty = $this->parser->parseText($this->user, 'مرحبا كيفك');

    expect($empty->items)->toBe([])
        ->and($empty->questions)->toBe([__('coach.draft.no_food_found')])
        ->and($empty->requiresConfirmation)->toBeTrue()
        ->and(fn () => $this->drafts->commit($this->user, $empty, LogSource::Text))
        ->toThrow(fn (FoodDraftNotReadyException $e) => expect($e->reason)->toBe(FoodDraftNotReadyException::EMPTY));
});

it('commits a ready draft through FoodLogService::logMany', function () {
    aiFake([aiParsed([
        ['name' => 'بيض', 'quantity' => 2, 'unit' => 'piece'],
        ['name' => 'لحم عجل', 'quantity' => 140, 'unit' => 'g', 'state' => 'cooked'],
    ], 'dinner')]);

    $veal = aiFood([
        'name_ar' => 'لحم عجل هبرة (ني)', 'name_en' => 'Lean veal, raw', 'category' => 'meat', 'state' => 'raw', 'cooked_yield' => 0.70,
        'calories' => 150, 'protein_g' => 21, 'carbs_g' => 0, 'fiber_g' => 0, 'fat_g' => 7, 'saturated_fat_g' => 3,
    ], ['لحم عجل']);

    $draft = $this->parser->parseText($this->user, 'تعشيت بيضتين و١٤٠ غرام لحم عجل مطبوخ', null, CarbonImmutable::parse('2026-11-08'));
    $entries = $this->drafts->commit($this->user, $draft, LogSource::Voice);

    expect($entries)->toHaveCount(2)
        ->and(FoodLogEntry::count())->toBe(2)
        ->and($entries[0]->user_id)->toBe($this->user->id)
        ->and($entries[0]->source)->toBe(LogSource::Voice)
        ->and($entries[0]->meal)->toBe(Meal::Dinner)
        ->and($entries[0]->logged_on->toDateString())->toBe('2026-11-08')
        ->and($entries[0]->group_id)->toBe($entries[1]->group_id)
        ->and($entries[0]->calories)->toBe($this->log->preview($this->foods['egg'], 2, 'piece')->calories)
        ->and($entries[1]->food_id)->toBe($veal->id)
        ->and($entries[1]->weighed_cooked)->toBeTrue()
        ->and($entries[1]->grams)->toEqualWithDelta(200.0, 0.001)
        ->and($entries[1]->calories)->toEqualWithDelta(300.0, 0.001);
});

it('surfaces configuration, rate-limit, refusal and malformed-answer problems as typed exceptions', function () {
    config(['services.anthropic.key' => '']);
    Http::fake();
    expect(fn () => $this->parser->parseText($this->user, 'بيض'))->toThrow(AiNotConfiguredException::class);
    Http::assertNothingSent();

    aiConfigured();

    expect(fn () => $this->parser->parseText($this->user, '  '))->toThrow(ValidationException::class);

    aiFake([aiText('this is not json')]);
    expect(fn () => $this->parser->parseText($this->user, 'بيض'))
        ->toThrow(fn (AiUnavailableException $e) => expect($e->reason)->toBe(AiUnavailableException::BAD_RESPONSE));

    aiFake([aiText('', ['content' => [], 'stop_reason' => 'refusal', 'stop_details' => ['type' => 'refusal', 'category' => null]])]);
    expect(fn () => $this->parser->parseText($this->user, 'بيض'))
        ->toThrow(fn (AiUnavailableException $e) => expect($e->reason)->toBe(AiUnavailableException::REFUSAL));

    config(['ai.rate_limit.parser.per_minute' => 2]);
    aiFake([aiParsed([])]);
    expect(fn () => $this->parser->parseText($this->user, 'بيض'))->toThrow(AiRateLimitException::class);
});

it('never puts the API key into a draft', function () {
    aiFake([aiParsed([['name' => 'بيض', 'quantity' => 2, 'unit' => 'piece']])]);

    $draft = $this->parser->parseText($this->user, 'بيضتين');

    expect(json_encode($draft->toArray()).serialize($draft).var_export($draft, true))->not->toContain(AI_TEST_KEY);
});

it('has a translation for every draft issue and status in both languages', function () {
    $issues = ['no_match', 'food_not_accessible', 'ambiguous_match', 'estimated_values', 'low_confidence', 'visual_estimate',
        'missing_quantity', 'missing_unit', 'unknown_unit', 'cooked_weight_unconvertible', 'raw_or_cooked_unknown', 'not_calculable'];

    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach ($issues as $issue) {
            expect(__('coach.draft.issues.'.$issue))->not->toStartWith('coach.');
        }
        foreach ([FoodDraft::RESOLVED, FoodDraft::AMBIGUOUS, FoodDraft::UNRESOLVED, FoodDraft::NEEDS_QUANTITY] as $status) {
            expect(__('coach.draft.status.'.$status))->not->toStartWith('coach.');
        }
    }

    expect(array_keys(Arr::dot(require lang_path('ar/coach.php'))))
        ->toBe(array_keys(Arr::dot(require lang_path('en/coach.php'))));
});

it('resolves Syrian-dialect names against the real catalogue', function () {
    if (! class_exists(FoodCatalogueSeeder::class)) {
        $this->markTestSkipped('The food catalogue seeder is not available.');
    }

    Food::query()->delete();
    $this->seed(FoodCatalogueSeeder::class);

    aiFake([aiParsed([
        ['name' => 'شيش طاووق', 'alt_names' => ['صدر دجاج مشوي'], 'quantity' => 200, 'unit' => 'غرام', 'cooking_method' => 'grilled', 'state' => 'cooked'],
        ['name' => 'مكدوس', 'quantity' => 2, 'unit' => 'حبة'],
        ['name' => 'سمنة عربية', 'alt_names' => ['سمنة', 'ghee'], 'quantity' => 1, 'unit' => 'ملعقة كبيرة', 'is_cooking_fat' => true],
        ['name' => 'بيضتين', 'alt_names' => ['بيض'], 'quantity' => 2, 'unit' => 'piece'],
        ['name' => 'لحمة', 'quantity' => 150, 'unit' => 'g'],
        ['name' => 'كباب', 'quantity' => 3, 'unit' => 'سيخ', 'state' => 'cooked'],
    ], 'lunch')]);

    $draft = $this->parser->parseText($this->user, 'تغديت ٢٠٠ غرام شيش طاووق وحبتين مكدوس وبيضتين مقليات بمعلقة سمنة عربية و١٥٠ غرام لحمة و٣ أسياخ كباب');
    $slug = fn (string $spoken) => Food::find(($this->item)($draft, $spoken)['food_id'])?->slug;

    expect($slug('شيش طاووق'))->toBe('chicken-breast-cooked')
        ->and(($this->item)($draft, 'شيش طاووق')['status'])->toBe(FoodDraft::RESOLVED)
        ->and(($this->item)($draft, 'شيش طاووق')['weighed_cooked'])->toBeFalse()
        ->and($slug('مكدوس'))->toBe('makdous-drained')
        ->and(($this->item)($draft, 'مكدوس')['unit'])->toBe('piece')
        ->and(($this->item)($draft, 'مكدوس')['status'])->toBe(FoodDraft::RESOLVED)
        ->and($slug('سمنة عربية'))->toBe('ghee-animal')
        ->and(($this->item)($draft, 'سمنة عربية')['unit'])->toBe('tbsp')
        ->and($slug('بيضتين'))->toBe('egg-whole-raw')
        ->and(($this->item)($draft, 'بيضتين')['status'])->toBe(FoodDraft::RESOLVED)
        // "لحمة" could be several meats: the user has to choose
        ->and(($this->item)($draft, 'لحمة')['status'])->toBe(FoodDraft::AMBIGUOUS)
        ->and($slug('كباب'))->toBe('kebab-grilled')
        ->and(($this->item)($draft, 'كباب')['unit'])->toBe('skewer')
        ->and(($this->item)($draft, 'كباب')['status'])->toBe(FoodDraft::RESOLVED);

    foreach ($draft->items as $item) {
        $food = Food::with('units')->find($item['food_id']);
        expect($item['nutrition'])->toBe($this->log->preview($food, $item['quantity'], $item['unit'], $item['weighed_cooked'])->toArray());
    }
});
