<?php

use App\Enums\LogSource;
use App\Enums\Meal;
use App\Models\CoachMessage;
use App\Models\CoachProposal;
use App\Models\FoodLogEntry;
use App\Models\MealPlan;
use App\Models\Profile;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Ai\Coach\CoachService;
use App\Services\Ai\Proposals\ProposalInvalidException;
use App\Services\Ai\Proposals\ProposalService;
use App\Services\Ai\Proposals\ProposalStateException;
use App\Services\Ai\Proposals\ProposalStatus;
use App\Services\Ai\Proposals\ProposalType;
use App\Services\Logging\FoodLogService;
use App\Services\Plans\MealPlanService;
use App\Services\Recipes\RecipeService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

require_once __DIR__.'/support.php';

beforeEach(function () {
    aiConfigured();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 13:00:00', 'Asia/Damascus'));
    Carbon\Carbon::setTestNow(CarbonImmutable::now());

    $this->user = User::factory()->create(['locale' => 'ar']);
    Profile::factory()->create(['user_id' => $this->user->id]);
    $this->conversation = app(CoachService::class)->startConversation($this->user);
    $this->foods = aiSeedFoods();
    $this->proposals = app(ProposalService::class);
    $this->log = app(FoodLogService::class);

    $this->mealPayload = fn (float $eggs = 2) => [
        'meal' => 'breakfast',
        'items' => [
            ['food_id' => $this->foods['egg']->id, 'quantity' => $eggs, 'unit' => 'piece'],
            ['food_id' => $this->foods['oil']->id, 'quantity' => 1, 'unit' => 'tbsp'],
        ],
    ];

    $this->notes = fn () => CoachMessage::where('role', 'note')->orderBy('id')->get()->map->displayText()->all();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon\Carbon::setTestNow();
});

it('stores a pending proposal with engine numbers and writes nothing else', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());

    $egg = $this->log->preview($this->foods['egg'], 2, 'piece');
    $oil = $this->log->preview($this->foods['oil'], 1, 'tbsp');

    expect($proposal->status)->toBe(ProposalStatus::Pending)
        ->and($proposal->isPending())->toBeTrue()
        ->and($proposal->expires_at->equalTo(now()->addMinutes(30)))->toBeTrue()
        ->and($proposal->preview['totals'])->toEqual($egg->plus($oil)->toArray())
        ->and($proposal->preview['day']['remaining_after']['calories'])->toEqual(1900 - $egg->plus($oil)->calories)
        ->and($proposal->preview['is_estimate'])->toBeFalse()
        ->and(FoodLogEntry::count())->toBe(0)
        ->and(($this->notes)())->toBe([]);
});

it('confirms: executes through FoodLogService, stores the ids and tells the conversation', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());

    $confirmed = $this->proposals->confirm($this->user, $proposal);
    $entries = FoodLogEntry::where('user_id', $this->user->id)->orderBy('id')->get();

    expect($confirmed->status)->toBe(ProposalStatus::Confirmed)
        ->and($entries)->toHaveCount(2)
        ->and($confirmed->result)->toBe(['entry_ids' => $entries->pluck('id')->all()])
        ->and($entries[0]->source)->toBe(LogSource::Coach)
        ->and($entries[0]->meal)->toBe(Meal::Breakfast)
        ->and($entries[0]->logged_on->toDateString())->toBe('2026-11-10')
        ->and($entries[0]->group_id)->toBe($entries[1]->group_id)
        ->and($entries[0]->nutrients()->toArray())->toEqual($proposal->preview['items'][0]['nutrition'])
        ->and(($this->notes)())->toBe([
            "Proposal #{$proposal->id} (log_meal) was CONFIRMED by the user and the database write succeeded. Food log entries [{$entries[0]->id}, {$entries[1]->id}] were logged.",
        ]);
});

it('applies the user\'s edits and recalculates before writing', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());

    $edits = [
        'meal' => 'dinner',
        'date' => '2026-11-09',
        'items' => [['food_id' => $this->foods['egg']->id, 'quantity' => 3, 'unit' => 'piece']],   // 3 eggs, oil removed
    ];

    // live preview for the edit form – nothing is saved by it
    $preview = $this->proposals->preview($this->user, $proposal, $edits);
    $engine = $this->log->preview($this->foods['egg'], 3, 'piece');

    expect($preview['totals'])->toEqual($engine->toArray())
        ->and($proposal->fresh()->status)->toBe(ProposalStatus::Pending)
        ->and(FoodLogEntry::count())->toBe(0);

    $confirmed = $this->proposals->confirm($this->user, $proposal, $edits);
    $entries = FoodLogEntry::all();

    expect($confirmed->status)->toBe(ProposalStatus::Confirmed)
        ->and($entries)->toHaveCount(1)
        ->and($entries[0]->quantity)->toBe(3.0)
        ->and($entries[0]->meal)->toBe(Meal::Dinner)
        ->and($entries[0]->logged_on->toDateString())->toBe('2026-11-09')
        ->and($entries[0]->nutrients()->toArray())->toEqual($engine->toArray())
        // the stored proposal reflects what was really executed
        ->and($confirmed->fresh()->preview['totals'])->toEqual($engine->toArray())
        ->and($confirmed->fresh()->payload['items'])->toHaveCount(1)
        ->and(($this->notes)()[0])->toContain('The user edited it before confirming (meal, date, items)');
});

it('ignores edit keys the type does not allow', function () {
    $entry = $this->log->logFood($this->user, $this->foods['egg'], 1, 'piece', Meal::Lunch, CarbonImmutable::parse('2026-11-10'));
    $other = $this->log->logFood($this->user, $this->foods['egg'], 5, 'piece', Meal::Lunch, CarbonImmutable::parse('2026-11-10'));

    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::DeleteLogEntry, ['entry_id' => $entry->id]);
    $this->proposals->confirm($this->user, $proposal, ['entry_id' => $other->id]);

    expect(FoodLogEntry::find($entry->id))->toBeNull()
        ->and(FoodLogEntry::find($other->id))->not->toBeNull();
});

it('keeps the proposal pending when the user\'s edits are invalid', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());

    try {
        $this->proposals->confirm($this->user, $proposal, ['items' => [['food_id' => $this->foods['egg']->id, 'quantity' => 1, 'unit' => 'cup']]]);
        $this->fail('expected ProposalInvalidException');
    } catch (ProposalInvalidException $e) {
        expect($e->errors[0]['code'])->toBe('unknown_unit')
            ->and($e->errors[0]['field'])->toBe('items.0.unit')
            ->and($e->messagesByField())->toHaveKey('items.0.unit');
    }

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Pending)
        ->and(FoodLogEntry::count())->toBe(0)
        ->and(($this->notes)())->toBe([]);

    // and it can still be confirmed afterwards
    expect($this->proposals->confirm($this->user, $proposal->fresh())->status)->toBe(ProposalStatus::Confirmed);
});

it('cannot be confirmed twice', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());

    $this->proposals->confirm($this->user, $proposal);

    expect(fn () => $this->proposals->confirm($this->user, $proposal->fresh()))
        ->toThrow(fn (ProposalStateException $e) => expect($e->reason)->toBe(ProposalStateException::ALREADY_RESOLVED))
        // a stale model instance (two browser tabs) is refused as well
        ->and(fn () => $this->proposals->confirm($this->user, $proposal))->toThrow(ProposalStateException::class)
        ->and(fn () => $this->proposals->reject($this->user, $proposal))->toThrow(ProposalStateException::class);

    expect(FoodLogEntry::count())->toBe(2)
        ->and(($this->notes)())->toHaveCount(1);
});

it('rejects expired proposals and says so in the conversation', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());

    $this->travel(31)->minutes();

    expect($proposal->fresh()->isExpired())->toBeTrue()
        ->and($proposal->fresh()->isPending())->toBeFalse()
        ->and(fn () => $this->proposals->confirm($this->user, $proposal))
        ->toThrow(fn (ProposalStateException $e) => expect($e->reason)->toBe(ProposalStateException::EXPIRED));

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Expired)
        ->and(FoodLogEntry::count())->toBe(0)
        ->and(($this->notes)()[0])->toContain('expired without being confirmed. Nothing was saved');
});

it('expires stale proposals in bulk', function () {
    $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());
    $this->travel(31)->minutes();
    $fresh = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());

    expect($this->proposals->expireStale($this->user))->toBe(1)
        ->and($fresh->fresh()->status)->toBe(ProposalStatus::Pending);
});

it('records a rejection', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());

    $rejected = $this->proposals->reject($this->user, $proposal);

    expect($rejected->status)->toBe(ProposalStatus::Rejected)
        ->and(FoodLogEntry::count())->toBe(0)
        ->and(($this->notes)())->toBe(["Proposal #{$proposal->id} (log_meal) was REJECTED by the user. Nothing was saved or changed."]);
});

it('never lets another user confirm, reject or preview a proposal', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());
    $stranger = User::factory()->create();

    expect(fn () => $this->proposals->confirm($stranger, $proposal))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->proposals->reject($stranger, $proposal))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->proposals->preview($stranger, $proposal))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->proposals->propose($stranger, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)()))->toThrow(AuthorizationException::class)
        ->and($proposal->fresh()->status)->toBe(ProposalStatus::Pending)
        ->and(Gate::forUser($stranger)->allows('update', $proposal))->toBeFalse()
        ->and(Gate::forUser($this->user)->allows('update', $proposal))->toBeTrue()
        ->and(Gate::forUser($stranger)->allows('view', $this->conversation))->toBeFalse()
        ->and(Gate::forUser($this->user)->allows('view', $this->conversation))->toBeTrue()
        ->and(Gate::forUser($stranger)->allows('view', $this->conversation->messages()->first() ?? CoachMessage::create([
            'conversation_id' => $this->conversation->id, 'role' => 'user', 'content' => [], 'meta' => ['kind' => 'user'],
        ])))->toBeFalse();
});

it('marks the proposal failed, writes nothing and notes the failure when the database write fails', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());

    // the second insert of the meal blows up: the first must be rolled back with it
    $calls = 0;
    FoodLogEntry::creating(function () use (&$calls) {
        if (++$calls === 2) {
            throw new RuntimeException('SQLSTATE[HY000]: disk I/O error (secret internals)');
        }
    });

    $failed = $this->proposals->confirm($this->user, $proposal);

    FoodLogEntry::flushEventListeners();

    expect($calls)->toBe(2)
        ->and($failed->status)->toBe(ProposalStatus::Failed)
        ->and($failed->result)->toBe(['error' => __('coach.proposals.errors.save_failed')])
        ->and(FoodLogEntry::count())->toBe(0);

    $note = ($this->notes)()[0];

    expect($note)->toContain('the save FAILED', 'NOTHING was saved')
        ->and($note)->not->toContain('were logged', 'CONFIRMED', 'SQLSTATE', 'secret internals');

    // a failed proposal is final
    expect(fn () => $this->proposals->confirm($this->user, $failed))->toThrow(ProposalStateException::class);

    // and the model is told the truth on its next turn
    aiFake([aiText('للأسف ما انحفظ شي، جرّب مرة تانية.')]);
    app(CoachService::class)->send($this->user, $this->conversation, 'انحفظ؟');

    expect(json_encode(aiRequests()[0]['messages']))->toContain('[APP NOTE] Proposal #'.$proposal->id, 'the save FAILED')
        ->and(json_encode(aiRequests()[0]['messages']))->not->toContain('were logged');
});

it('fails cleanly when a food disappeared between proposing and confirming', function () {
    $mine = aiFood(['user_id' => $this->user->id, 'name_ar' => 'خبز كيتو بيتي', 'name_en' => 'Home keto bread']);
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, [
        'meal' => 'lunch', 'items' => [['food_id' => $mine->id, 'quantity' => 50, 'unit' => 'g']],
    ]);

    $mine->delete();

    $failed = $this->proposals->confirm($this->user, $proposal);

    expect($failed->status)->toBe(ProposalStatus::Failed)
        ->and(FoodLogEntry::count())->toBe(0)
        ->and(($this->notes)()[0])->toContain('the save FAILED');
});

it('proposes and confirms an update and a delete of a diary entry', function () {
    $entry = $this->log->logFood($this->user, $this->foods['chickenRaw'], 200, 'g', Meal::Lunch, CarbonImmutable::parse('2026-11-10'));
    $before = $entry->nutrients()->toArray();

    $update = $this->proposals->propose($this->user, $this->conversation, ProposalType::UpdateLogEntry, [
        'entry_id' => $entry->id, 'changes' => ['quantity' => 150, 'meal' => 'dinner', 'date' => '2026-11-09'],
    ]);
    $engine = $this->log->preview($this->foods['chickenRaw'], 150, 'g');

    expect($update->payload['changes'])->toEqual(['meal' => 'dinner', 'logged_on' => '2026-11-09', 'quantity' => 150.0])
        ->and($update->preview['before']['nutrition'])->toEqual($before)
        ->and($update->preview['after']['nutrition'])->toEqual($engine->toArray())
        ->and($update->preview['after']['meal'])->toBe('dinner')
        ->and($entry->fresh()->quantity)->toBe(200.0);

    $this->proposals->confirm($this->user, $update);
    $entry->refresh();

    expect($entry->quantity)->toBe(150.0)
        ->and($entry->meal)->toBe(Meal::Dinner)
        ->and($entry->logged_on->toDateString())->toBe('2026-11-09')
        ->and($entry->calories)->toBe($engine->calories)
        ->and($entry->protein_g)->toBe($engine->protein)
        // the column keeps three decimals
        ->and($entry->saturated_fat_g)->toEqualWithDelta($engine->saturatedFat, 0.0005);

    $delete = $this->proposals->propose($this->user, $this->conversation, ProposalType::DeleteLogEntry, ['entry_id' => $entry->id]);

    expect($delete->preview['entry']['entry_id'])->toBe($entry->id)
        ->and(FoodLogEntry::count())->toBe(1);

    $deleted = $this->proposals->confirm($this->user, $delete);

    expect(FoodLogEntry::count())->toBe(0)
        ->and($deleted->result)->toBe(['deleted_entry_ids' => [$entry->id]]);

    // unknown fields are refused
    expect(fn () => $this->proposals->propose($this->user, $this->conversation, ProposalType::UpdateLogEntry, [
        'entry_id' => 999999, 'changes' => ['calories' => 1],
    ]))->toThrow(ProposalInvalidException::class);
});

it('proposes a recipe with RecipeService numbers and saves it only on confirm', function () {
    $payload = [
        'name' => 'عجة كيتو',
        'servings' => 2,
        'instructions' => 'اخفق البيض واقليه بالزيت.',
        'ingredients' => [
            ['food_id' => $this->foods['egg']->id, 'quantity' => 4, 'unit' => 'piece'],
            ['food_id' => $this->foods['oil']->id, 'quantity' => 2, 'unit' => 'tbsp'],
            ['food_id' => $this->foods['labneh']->id, 'quantity' => 30, 'unit' => 'g'],
        ],
    ];

    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::SaveRecipe, $payload);

    expect(Recipe::count())->toBe(0)
        ->and($proposal->preview['is_estimate'])->toBeTrue()
        ->and($proposal->preview['total']['missing'])->toBe(['fiber', 'saturated_fat'])
        ->and($proposal->preview['raw_weight_g'])->toEqual(257.0)
        ->and($proposal->preview['finished_weight_is_measured'])->toBeFalse();

    $confirmed = $this->proposals->confirm($this->user, $proposal, ['servings' => 4]);
    $recipe = Recipe::findOrFail($confirmed->result['recipe_id']);
    $nutrition = app(RecipeService::class)->nutrition($recipe);

    expect($recipe->user_id)->toBe($this->user->id)
        ->and($recipe->origin)->toBe('ai')
        ->and($recipe->servings)->toBe(4.0)
        ->and($recipe->ingredients)->toHaveCount(3)
        // preview of the executed (edited) payload == what the saved recipe computes
        ->and($confirmed->fresh()->preview['per_serving'])->toEqual($nutrition->perServing->toArray())
        ->and($confirmed->fresh()->preview['total'])->toEqual($nutrition->total->toArray())
        ->and($proposal->preview['total'])->toEqual($nutrition->total->toArray());
});

it('proposes a meal plan with per-day engine totals and saves it only on confirm', function () {
    $recipe = app(RecipeService::class)->save($this->user, [
        'name' => 'بيض بزيت', 'servings' => 1,
        'ingredients' => [['food_id' => $this->foods['egg']->id, 'quantity' => 2, 'unit' => 'piece']],
    ]);

    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::MealPlan, [
        'name' => 'خطة يومين', 'starts_on' => 'tomorrow', 'days' => 2,
        'items' => [
            ['planned_on' => '2026-11-11', 'meal' => 'breakfast', 'recipe_id' => $recipe->id, 'quantity' => 1, 'unit' => 'serving'],
            ['planned_on' => '2026-11-11', 'meal' => 'lunch', 'food_id' => $this->foods['chickenCooked']->id, 'quantity' => 200, 'unit' => 'g'],
            ['planned_on' => '2026-11-12', 'meal' => 'dinner', 'food_id' => $this->foods['labneh']->id, 'quantity' => 2, 'unit' => 'tbsp', 'note' => 'مع خيار'],
        ],
    ]);

    expect(MealPlan::count())->toBe(0)
        ->and($proposal->preview['starts_on'])->toBe('2026-11-11')
        ->and($proposal->preview['by_day'])->toHaveCount(2);

    $confirmed = $this->proposals->confirm($this->user, $proposal);
    $plan = MealPlan::findOrFail($confirmed->result['meal_plan_id']);
    $plans = app(MealPlanService::class);

    expect($plan->origin)->toBe('ai')
        ->and($plan->items)->toHaveCount(3)
        ->and($proposal->preview['by_day'][0]['totals'])->toEqual($plans->dayNutrition($plan, CarbonImmutable::parse('2026-11-11'))->toArray())
        ->and($proposal->preview['by_day'][1]['totals'])->toEqual($plans->dayNutrition($plan, CarbonImmutable::parse('2026-11-12'))->toArray())
        ->and($proposal->preview['by_day'][1]['is_estimate'])->toBeTrue()
        ->and($proposal->preview['by_day'][0]['remaining_vs_targets']['net_carbs_g'])
        ->toEqual(25 - $plans->dayNutrition($plan, CarbonImmutable::parse('2026-11-11'))->netCarbs());

    // items outside the plan period are refused up front
    expect(fn () => $this->proposals->propose($this->user, $this->conversation, ProposalType::MealPlan, [
        'name' => 'x', 'starts_on' => '2026-11-11', 'days' => 1,
        'items' => [['planned_on' => '2026-11-20', 'meal' => 'lunch', 'food_id' => $this->foods['egg']->id, 'quantity' => 1, 'unit' => 'piece']],
    ]))->toThrow(fn (ProposalInvalidException $e) => expect($e->errors[0]['code'])->toBe('outside_plan_range'));
});

it('converts a cooked weight through the food\'s yield and refuses it when there is none', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, [
        'meal' => 'lunch',
        'items' => [['food_id' => $this->foods['chickenRaw']->id, 'quantity' => 150, 'unit' => 'g', 'weighed_cooked' => true]],
    ]);

    // 150 g cooked / 0.75 = 200 g raw
    expect($proposal->preview['totals'])->toEqual($this->log->preview($this->foods['chickenRaw'], 200, 'g')->toArray())
        ->and($proposal->payload['items'][0]['weighed_cooked'])->toBeTrue();

    $noYield = aiFood(['name_ar' => 'لحم غنم ني', 'name_en' => 'Lamb raw', 'state' => 'raw', 'cooked_yield' => null]);

    expect(fn () => $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, [
        'meal' => 'lunch', 'items' => [['food_id' => $noYield->id, 'quantity' => 150, 'unit' => 'g', 'weighed_cooked' => true]],
    ]))->toThrow(fn (ProposalInvalidException $e) => expect($e->errors[0]['code'])->toBe('no_cooked_yield'));

    expect(CoachProposal::count())->toBe(1);
});

it('has translated summaries and statuses in both languages', function () {
    $proposal = $this->proposals->propose($this->user, $this->conversation, ProposalType::LogMeal, ($this->mealPayload)());

    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);

        expect($proposal->summary())->not->toContain('coach.')
            ->and($proposal->summary())->toContain('2026-11-10');

        foreach (ProposalStatus::cases() as $status) {
            expect($status->label())->not->toContain('coach.');
        }
    }
});
