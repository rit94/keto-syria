<?php

use App\Enums\LogSource;
use App\Livewire\SmartLog\Capture;
use App\Models\FoodLogEntry;
use App\Models\Profile;
use App\Models\User;
use App\Services\Ai\SmartLog\FoodDraft;
use App\Services\Logging\DailySummaryService;
use App\Services\Logging\FoodLogService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

require_once __DIR__.'/../Ai/support.php';

beforeEach(function () {
    aiConfigured();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 13:00:00', 'Asia/Damascus'));
    Carbon\Carbon::setTestNow(CarbonImmutable::now());

    $this->user = User::factory()->create(['locale' => 'en']);
    Profile::factory()->create(['user_id' => $this->user->id]);
    $this->foods = aiSeedFoods();
    app()->setLocale('en');
    $this->actingAs($this->user);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon\Carbon::setTestNow();
});

/** Analyse a text description scripted as the given parsed items. */
function smartLogAnalyze($test, array $items, string $text = 'two eggs and a spoon of olive oil', string $mode = 'text', array $questions = [])
{
    aiFake([aiParsed($items, 'breakfast', $questions)]);

    return Livewire::test(Capture::class)->set('mode', $mode)->set('text', $text)->call('analyze');
}

function eggAndOil(): array
{
    return [
        ['name' => 'بيض', 'alt_names' => ['egg'], 'quantity' => 2, 'unit' => 'piece'],
        ['name' => 'زيت زيتون', 'alt_names' => ['olive oil'], 'quantity' => 1, 'unit' => 'tbsp', 'is_cooking_fat' => true],
    ];
}

it('renders in Arabic (rtl) and in English', function () {
    $this->get('/log')->assertOk()->assertSee('dir="ltr"', false)->assertSee(__('smart_log.text.label'));

    app()->setLocale('ar');
    $this->user->forceFill(['locale' => 'ar'])->save();

    $this->get('/log')->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('smart_log.text.label'), false)
        ->assertSee(__('smart_log.mode.voice'), false);
});

it('parses a description into an editable draft with engine numbers and logs nothing yet', function () {
    $component = smartLogAnalyze($this, eggAndOil())
        ->assertSee('Egg, whole, raw')
        ->assertSee('Olive oil')
        ->assertSee('Total of this meal')
        ->assertSee('Left for the day after this')
        ->assertSet('meal', 'breakfast');

    $draft = FoodDraft::fromArray($component->get('draft'));
    $expected = app(FoodLogService::class)->preview($this->foods['egg'], 2, 'piece')
        ->plus(app(FoodLogService::class)->preview($this->foods['oil'], 1, 'tbsp'));

    expect(FoodLogEntry::count())->toBe(0)
        ->and($draft->totals['calories'])->toEqualWithDelta($expected->calories, 0.0001)
        ->and($draft->isCommittable())->toBeTrue();
});

it('edits a quantity, then logs through the draft service with the text source and a shared group', function () {
    $component = smartLogAnalyze($this, eggAndOil());
    $draft = FoodDraft::fromArray($component->get('draft'));
    $eggKey = collect($draft->items)->firstWhere('food_id', $this->foods['egg']->id)['key'];

    $component
        ->set("rows.$eggKey.quantity", '3')
        ->set('meal', 'lunch')
        ->call('commit')
        ->assertSet('draft', null)
        ->assertSee('Logged')
        ->assertSee('Open today');

    $entries = FoodLogEntry::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('source')->unique()->all())->toBe([LogSource::Text])
        ->and($entries->pluck('group_id')->unique())->toHaveCount(1)
        ->and($entries->pluck('group_id')->first())->not->toBeNull()
        ->and($entries[0]->quantity)->toBe(3.0)
        ->and($entries[0]->meal->value)->toBe('lunch');

    $summary = app(DailySummaryService::class)->forDate($this->user, CarbonImmutable::parse('2026-11-10'));
    expect($summary->totals->calories)->toEqualWithDelta($entries->sum('calories'), 0.0001);
});

it('records the voice source when the transcript came from the voice tab', function () {
    smartLogAnalyze($this, eggAndOil(), 'بيضتين وملعقة زيت', 'voice')
        ->assertSet('source', 'voice')
        ->call('commit')
        ->assertSee('Logged');

    expect(FoodLogEntry::pluck('source')->unique()->all())->toBe([LogSource::Voice]);
});

it('blocks the commit while an ambiguous food is not confirmed', function () {
    $white = aiFood(['name_ar' => 'جبنة بيضاء بلدية', 'name_en' => 'White cheese', 'category' => 'cheese', 'state' => 'na'], ['جبنة']);
    $yellow = aiFood(['name_ar' => 'جبنة قشقوان', 'name_en' => 'Kashkaval', 'category' => 'cheese', 'state' => 'na'], ['جبنة', 'قشقوان']);

    $component = smartLogAnalyze($this, [['name' => 'جبنة', 'quantity' => 50, 'unit' => 'g']], 'جبنة 50 غرام')
        ->assertSee('Please check the food')
        ->assertSee('Yes, this is the right food');

    $key = FoodDraft::fromArray($component->get('draft'))->items[0]['key'];

    $component->call('commit')
        ->assertSee('Confirm or change the foods')
        ->assertNotSet('draft', null);
    expect(FoodLogEntry::count())->toBe(0);

    // confirming the suggestion (or choosing another candidate) unblocks it
    $component->call('chooseCandidate', $key, $yellow->id)
        ->assertDontSee('Please check the food');

    if (FoodDraft::fromArray($component->get('draft'))->requiresConfirmation) {
        $component->set('acknowledged', true);
    }

    $component->call('commit')->assertSee('Logged');

    expect(FoodLogEntry::count())->toBe(1)
        ->and(FoodLogEntry::first()->food_id)->toBe($yellow->id);
});

it('enables confirm after confirmFood on the suggested food', function () {
    aiFood(['name_ar' => 'جبنة بيضاء بلدية', 'name_en' => 'White cheese', 'category' => 'cheese', 'state' => 'na'], ['جبنة']);
    aiFood(['name_ar' => 'جبنة قشقوان', 'name_en' => 'Kashkaval', 'category' => 'cheese', 'state' => 'na'], ['جبنة', 'قشقوان']);

    $component = smartLogAnalyze($this, [['name' => 'جبنة', 'quantity' => 50, 'unit' => 'g']], 'جبنة');
    $key = FoodDraft::fromArray($component->get('draft'))->items[0]['key'];

    $component->call('confirmFood', $key);

    expect(FoodDraft::fromArray($component->get('draft'))->items[0]['status'])->toBe(FoodDraft::RESOLVED);

    if (FoodDraft::fromArray($component->get('draft'))->requiresConfirmation) {
        $component->call('commit')->assertHasErrors('acknowledged');
        expect(FoodLogEntry::count())->toBe(0);
        $component->set('acknowledged', true);
    }

    $component->call('commit')->assertSee('Logged');
    expect(FoodLogEntry::count())->toBe(1);
});

it('blocks the commit while an amount is missing and highlights the row', function () {
    $component = smartLogAnalyze($this, [
        ['name' => 'بيض', 'alt_names' => ['egg'], 'quantity' => null, 'unit' => null],
    ], 'بيض')
        ->assertSee('Amount needed')
        ->assertSee('Enter the amount.');

    $draft = FoodDraft::fromArray($component->get('draft'));
    expect($draft->isCommittable())->toBeFalse();

    $component->call('commit');

    expect(FoodLogEntry::count())->toBe(0);
    $key = $draft->items[0]['key'];

    // the user fills it in → resolved and loggable
    $component->set("rows.$key.quantity", '2')->set("rows.$key.unit", 'piece');

    expect(FoodDraft::fromArray($component->get('draft'))->isCommittable())->toBeTrue();

    $component->set('acknowledged', true)->call('commit')->assertSee('Logged');
    expect(FoodLogEntry::count())->toBe(1);
});

it('lists unresolved foods with a way to pick one', function () {
    smartLogAnalyze($this, [['name' => 'طعام غير موجود أبداً', 'quantity' => 1, 'unit' => 'piece']])
        ->assertSee('Food not found')
        ->assertSee('We could not find this food')
        ->assertSee('Search for the food');
});

it('removes an item and adds one with the shared food picker event', function () {
    $component = smartLogAnalyze($this, eggAndOil());
    $draft = FoodDraft::fromArray($component->get('draft'));
    $oilKey = collect($draft->items)->firstWhere('food_id', $this->foods['oil']->id)['key'];

    $component->call('removeItem', $oilKey);
    expect(FoodDraft::fromArray($component->get('draft'))->items)->toHaveCount(1);

    $component->call('openPicker')
        ->assertDispatched('open-modal')
        ->dispatch('food-picked', context: 'smart-log', foodId: $this->foods['labneh']->id, quantity: 30, unit: 'g', weighedCooked: false, meal: null)
        ->assertDispatched('close-modal');

    $items = FoodDraft::fromArray($component->get('draft'))->items;
    expect($items)->toHaveCount(2)
        ->and($items[1]['food_id'])->toBe($this->foods['labneh']->id)
        ->and($items[1]['quantity'])->toBe(30.0);

    // an event from another picker is ignored
    $component->dispatch('food-picked', context: 'recipe', foodId: $this->foods['oil']->id, quantity: 5, unit: 'g', weighedCooked: false, meal: null);
    expect(FoodDraft::fromArray($component->get('draft'))->items)->toHaveCount(2);
});

it('changes the food of a row through the picker event', function () {
    $component = smartLogAnalyze($this, [['name' => 'جبنة غير معروفة', 'quantity' => 40, 'unit' => 'g']]);
    $key = FoodDraft::fromArray($component->get('draft'))->items[0]['key'];

    $component->call('openPicker', $key)
        ->dispatch('food-picked', context: 'smart-log', foodId: $this->foods['labneh']->id, quantity: 40, unit: 'g', weighedCooked: false, meal: null);

    $item = FoodDraft::fromArray($component->get('draft'))->items[0];

    expect($item['food_id'])->toBe($this->foods['labneh']->id)
        ->and($item['status'])->toBe(FoodDraft::RESOLVED);
});

it('shows clarification questions as inputs and re-parses with the answer', function () {
    $component = smartLogAnalyze($this, [
        ['name' => 'صدر فروج', 'alt_names' => ['chicken breast'], 'quantity' => null, 'unit' => null],
    ], 'صدر فروج', questions: ['How much chicken did you eat?'])
        ->assertSee('How much chicken did you eat?');

    aiFake([aiParsed([
        ['name' => 'صدر فروج', 'alt_names' => ['chicken breast'], 'quantity' => 150, 'unit' => 'g', 'state' => 'cooked'],
    ], 'dinner')]);

    $component->set('answers.0', '150 g grilled')->call('answerQuestion', 0);

    $request = aiRequests()[0];
    expect(json_encode($request))->toContain('150 g grilled')
        ->and(FoodDraft::fromArray($component->get('draft'))->questions)->toBe([]);

    expect(FoodDraft::fromArray($component->get('draft'))->items[0]['quantity'])->toBe(150.0);
});

it('flags a photo draft as a visual estimate and always asks for a confirmation tick', function () {
    aiFake([aiParsed([
        ['name' => 'صدر فروج مشوي', 'alt_names' => ['grilled chicken breast'], 'quantity' => 180, 'unit' => 'g', 'state' => 'cooked'],
    ])]);

    $component = Livewire::test(Capture::class)
        ->set('mode', 'photo')
        ->set('photo', UploadedFile::fake()->image('plate.jpg', 40, 40))
        ->call('analyze')
        ->assertSee('Amounts from a photo are estimates')
        ->assertSee('The amount was estimated from the photo')
        ->assertSet('source', 'photo');

    expect(FoodDraft::fromArray($component->get('draft'))->requiresConfirmation)->toBeTrue();

    // not ticked → nothing is logged
    $component->call('commit')->assertHasErrors('acknowledged');
    expect(FoodLogEntry::count())->toBe(0);

    $component->set('acknowledged', true)->call('commit')->assertSee('Logged');

    expect(FoodLogEntry::count())->toBe(1)
        ->and(FoodLogEntry::first()->source)->toBe(LogSource::Image);
});

it('validates the text and the photo', function () {
    Livewire::test(Capture::class)->set('text', '   ')->call('analyze')->assertHasErrors('text');

    Livewire::test(Capture::class)->set('mode', 'photo')->call('analyze')->assertHasErrors('photo');

    Livewire::test(Capture::class)->set('mode', 'photo')
        ->set('photo', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->assertSet('photo', null)
        ->assertHasErrors('photo');

    config(['ai.images.max_kb' => 1]);
    Livewire::test(Capture::class)->set('mode', 'photo')
        ->set('photo', UploadedFile::fake()->image('big.jpg', 300, 300))
        ->assertSet('photo', null)
        ->assertHasErrors('photo');
});

it('shows a clear state when the assistant is not configured and still offers manual logging', function () {
    config(['services.anthropic.key' => null]);
    Http::fake();

    Livewire::test(Capture::class)
        ->assertSee('Smart logging is not set up yet')
        ->assertSee('Log food manually')
        ->assertSee('Ask the coach')
        ->assertDontSee('sk-ant');

    Livewire::test(Capture::class)->set('text', 'two eggs')->call('analyze')->assertSet('notice.type', 'not_configured');

    Http::assertNothingSent();
});

it('reports rate limits and an unavailable assistant', function () {
    config(['ai.rate_limit.parser.per_minute' => 1]);
    aiFake([aiParsed([['name' => 'بيض', 'quantity' => 1, 'unit' => 'piece']])]);

    $component = Livewire::test(Capture::class)->set('text', 'one egg')->call('analyze');
    $component->call('startOver')->set('text', 'one egg')->call('analyze')
        ->assertSet('notice.type', 'rate_limited')
        ->assertSee('too quickly');

    config(['ai.rate_limit.parser.per_minute' => 15]);
    $bad = [['error' => ['type' => 'api_error', 'message' => 'x']], 500];
    aiFake([$bad, $bad, $bad]);

    Livewire::test(Capture::class)->set('text', 'one egg')->call('analyze')
        ->assertSet('notice.type', 'unavailable')
        ->assertSee('Try again');
});

it('does not trust a tampered draft: unresolved or foreign data cannot be logged', function () {
    $component = smartLogAnalyze($this, eggAndOil());
    $draft = $component->get('draft');
    $draft['items'][0]['food_id'] = 999999;
    $draft['items'][0]['nutrition'] = ['calories' => 1.0];
    $draft['items'][0]['status'] = 'resolved';

    $component->set('draft', $draft)->call('commit');

    expect(FoodLogEntry::count())->toBe(0);
});

it('never exposes the api key in the page or in the livewire payload', function () {
    $component = smartLogAnalyze($this, eggAndOil());

    expect($this->get('/log')->getContent())->not->toContain(AI_TEST_KEY)->not->toContain('sk-ant')
        ->and($component->html())->not->toContain(AI_TEST_KEY)
        ->and(json_encode($component->get('draft')))->not->toContain(AI_TEST_KEY);
});
