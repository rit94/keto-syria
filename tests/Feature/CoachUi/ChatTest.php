<?php

use App\Enums\LogSource;
use App\Livewire\Coach\Chat;
use App\Models\CoachConversation;
use App\Models\CoachMessage;
use App\Models\CoachProposal;
use App\Models\FoodLogEntry;
use App\Models\Profile;
use App\Models\User;
use App\Services\Ai\Coach\CoachService;
use App\Services\Ai\Proposals\ProposalStatus;
use App\Services\Logging\DailySummaryService;
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

/** A conversation where the coach answered with a pending "log breakfast" proposal. */
function coachWithMealProposal($test, ?array $items = null): array
{
    $egg = $test->foods['egg'];
    $oil = $test->foods['oil'];

    aiFake([
        aiToolUse([['propose_log_meal', [
            'meal' => 'breakfast',
            'items' => $items ?? [
                ['food_id' => $egg->id, 'quantity' => 2, 'unit' => 'piece'],
                ['food_id' => $oil->id, 'quantity' => 1, 'unit' => 'tbsp'],
            ],
        ], 'toolu_p']]),
        aiText('I prepared your breakfast. **Please confirm** it.'),
    ]);

    $component = Livewire::test(Chat::class)
        ->set('message', 'I had two eggs with a spoon of olive oil')
        ->call('send');

    return [$component, CoachProposal::query()->latest('id')->first()];
}

it('renders the empty chat in Arabic (rtl) and in English', function () {
    $this->get('/coach')->assertOk()->assertSee('dir="ltr"', false)->assertSee(__('coach_ui.empty.title'));

    app()->setLocale('ar');
    $this->user->forceFill(['locale' => 'ar'])->save();

    $this->get('/coach')->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('coach_ui.empty.title'), false)
        ->assertSee(__('coach_ui.starters.tonight.label'), false);
});

it('sends a message, shows the reply and persists both sides', function () {
    aiFake([aiText("Eggs are great.\n\n- Protein\n- Fat\n\nUse **olive oil**.")]);

    $component = Livewire::test(Chat::class)
        ->set('message', 'Are eggs keto?')
        ->call('send')
        ->assertSet('message', '')
        ->assertSee('Are eggs keto?')
        ->assertSee('Eggs are great.')
        ->assertSeeHtml('<strong>olive oil</strong>')
        ->assertSeeHtml('<li>Protein</li>')
        ->assertDispatched('coach-scroll');

    $conversation = CoachConversation::first();

    expect($conversation->user_id)->toBe($this->user->id)
        ->and($conversation->title)->toBe('Are eggs keto?')
        ->and($component->get('conversationId'))->toBe($conversation->id)
        ->and($conversation->messages->filter->isVisible()->map->displayText()->values()->all())
        ->toBe(['Are eggs keto?', "Eggs are great.\n\n- Protein\n- Fat\n\nUse **olive oil**."]);

    // a reload shows the same history
    Livewire::test(Chat::class, ['conversation' => $conversation])->assertSee('Are eggs keto?')->assertSee('Eggs are great.');
});

it('escapes html in assistant text', function () {
    aiFake([aiText('<script>alert(1)</script> hello')]);

    Livewire::test(Chat::class)->set('message', 'hi')->call('send')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSee('hello');
});

it('shows a pending proposal with the engine numbers and writes nothing until confirmed', function () {
    [$component, $proposal] = coachWithMealProposal($this);

    expect(FoodLogEntry::count())->toBe(0)
        ->and($proposal->status)->toBe(ProposalStatus::Pending);

    $totals = $proposal->preview['totals'];

    $component
        ->assertSee('Waiting for your confirmation')
        ->assertSee('Not saved yet')
        ->assertSee('Egg, whole, raw')
        ->assertSee('Olive oil')
        ->assertSee(number_format($totals['calories'], 0, '.', ','))
        ->assertSee('Left for the day after this')
        ->assertSee('Confirm')
        ->assertSee('Decline');

    expect(FoodLogEntry::count())->toBe(0);
});

it('reloads a still-pending proposal with the conversation', function () {
    [, $proposal] = coachWithMealProposal($this);

    Livewire::test(Chat::class, ['conversation' => $proposal->conversation])
        ->assertSee('Waiting for your confirmation')
        ->assertSee('Egg, whole, raw');
});

it('logs the proposal on confirm and reports it only after the write succeeded', function () {
    [$component, $proposal] = coachWithMealProposal($this);

    $component->call('confirmProposal', $proposal->id)
        ->assertSee('Saved')
        ->assertSee('Open today')
        ->assertDispatched('toast');

    $proposal->refresh();
    $entries = FoodLogEntry::all();

    expect($proposal->status)->toBe(ProposalStatus::Confirmed)
        ->and($entries)->toHaveCount(2)
        ->and($entries->pluck('source')->unique()->all())->toBe([LogSource::Coach])
        ->and($entries->pluck('group_id')->unique())->toHaveCount(1);

    // a note about the real outcome was appended for the next turn
    expect(CoachMessage::where('role', 'note')->count())->toBe(1);
});

it('logs the edited quantities and the totals equal the daily summary', function () {
    [$component, $proposal] = coachWithMealProposal($this);
    $egg = $this->foods['egg'];

    $component->call('startEdit', $proposal->id)
        ->assertSet('editingId', $proposal->id)
        ->assertSet('edit.items.0.quantity', 2)
        ->set('edit.items.0.quantity', '3')
        ->set('edit.meal', 'lunch')
        ->call('removeRow', 'items', 1)
        ->call('confirmProposal', $proposal->id)
        ->assertSet('editingId', null)
        ->assertSee('Saved');

    $entries = FoodLogEntry::all();
    expect($entries)->toHaveCount(1)
        ->and($entries[0]->food_id)->toBe($egg->id)
        ->and($entries[0]->quantity)->toBe(3.0)
        ->and($entries[0]->meal->value)->toBe('lunch');

    $summary = app(DailySummaryService::class)->forDate($this->user, CarbonImmutable::parse('2026-11-10'));

    expect($summary->totals->calories)->toEqualWithDelta($entries[0]->calories, 0.0001)
        // 3 eggs of 50 g from the database facts, computed by the engine
        ->and($entries[0]->calories)->toEqualWithDelta(143 * 1.5, 0.001);
});

it('previews edits live through the proposal service and shows invalid fields inline', function () {
    [$component, $proposal] = coachWithMealProposal($this);

    $component->call('startEdit', $proposal->id)
        ->set('edit.items.0.quantity', '4')
        ->assertSee('New total')
        ->set('edit.items.0.quantity', '0')
        ->assertSee('Fix the highlighted fields')
        ->call('confirmProposal', $proposal->id)
        ->assertSet('editingId', $proposal->id);

    // nothing saved and the proposal is still pending, so the user can fix it
    expect(FoodLogEntry::count())->toBe(0)
        ->and($proposal->fresh()->status)->toBe(ProposalStatus::Pending);

    $component->set('edit.items.0.quantity', '2')->call('confirmProposal', $proposal->id)->assertSee('Saved');
    expect(FoodLogEntry::count())->toBe(2);
});

it('rejects a proposal without writing anything', function () {
    [$component, $proposal] = coachWithMealProposal($this);

    $component->call('rejectProposal', $proposal->id)
        ->assertSee('You declined this')
        ->assertDontSee('Confirm');

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Rejected)
        ->and(FoodLogEntry::count())->toBe(0);
});

it('explains a second confirm and an expired proposal', function () {
    [$component, $proposal] = coachWithMealProposal($this);

    $component->call('confirmProposal', $proposal->id);
    $second = Livewire::test(Chat::class, ['conversation' => $proposal->conversation])
        ->call('confirmProposal', $proposal->id)
        ->assertSee(__('coach.proposals.errors.already_resolved'));

    expect(FoodLogEntry::count())->toBe(2);

    [$component, $other] = coachWithMealProposal($this);
    $other->forceFill(['expires_at' => now()->subMinute()])->save();

    $component->call('confirmProposal', $other->id)
        ->assertSee(__('coach.proposals.errors.expired'));

    expect($other->fresh()->status)->toBe(ProposalStatus::Expired)
        ->and(FoodLogEntry::count())->toBe(2);
});

it('shows a failed save as failed and never claims success', function () {
    [$component, $proposal] = coachWithMealProposal($this);

    // the food disappears between proposing and confirming
    $this->foods['oil']->delete();

    $component->call('confirmProposal', $proposal->id)
        ->assertSee('Could not be saved')
        ->assertSee('Nothing was saved or changed')
        ->assertDontSee('Open today');

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Failed)
        ->and(FoodLogEntry::count())->toBe(0);
});

it('forbids other users from opening a conversation or touching a proposal', function () {
    [, $proposal] = coachWithMealProposal($this);

    $intruder = User::factory()->create(['locale' => 'en']);
    Profile::factory()->create(['user_id' => $intruder->id]);
    $this->actingAs($intruder);

    $this->get('/coach/'.$proposal->conversation_id)->assertForbidden();

    $own = app(CoachService::class)->startConversation($intruder);

    Livewire::test(Chat::class, ['conversation' => $own])
        ->call('confirmProposal', $proposal->id)
        ->assertForbidden();

    Livewire::test(Chat::class, ['conversation' => $own])
        ->call('rejectProposal', $proposal->id)
        ->assertForbidden();

    Livewire::test(Chat::class, ['conversation' => $own])->call('deleteConversation', $proposal->conversation_id)->assertForbidden();

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Pending)
        ->and(CoachConversation::count())->toBe(2);
});

it('does not confirm a proposal of another conversation of the same user', function () {
    [, $proposal] = coachWithMealProposal($this);
    $other = app(CoachService::class)->startConversation($this->user);

    Livewire::test(Chat::class, ['conversation' => $other])
        ->call('confirmProposal', $proposal->id)
        ->assertNotFound();

    expect(FoodLogEntry::count())->toBe(0);
});

it('offers the custom food form when the coach asks for it', function () {
    aiFake([
        aiToolUse([['request_custom_food', ['name' => 'شنكليش'], 'toolu_c']]),
        aiText('This food is not in the database yet.'),
    ]);

    Livewire::test(Chat::class)->set('message', 'I ate shanklish')->call('send')
        ->assertSee('is not in the food database')
        ->assertSeeHtml(e(route('foods.create', ['name' => 'شنكليش'])));
});

it('shows a clear state without api details when the coach is not configured', function () {
    config(['services.anthropic.key' => null]);
    Http::fake();

    Livewire::test(Chat::class)
        ->assertSee('The AI coach is not set up yet')
        ->assertSee('Log food manually')
        ->assertDontSee('sk-ant')
        ->assertDontSee('ANTHROPIC');

    // a send attempt still fails gracefully
    Livewire::test(Chat::class)->set('message', 'hello')->call('send')
        ->assertSet('notice.type', 'not_configured');

    expect(CoachConversation::count())->toBe(0);
    Http::assertNothingSent();
});

it('shows the rate limit with its wait time', function () {
    config(['ai.rate_limit.coach.per_minute' => 1]);
    aiFake([aiText('one')]);

    $component = Livewire::test(Chat::class)->set('message', 'first')->call('send');
    $component->set('message', 'second')->call('send')
        ->assertSet('notice.type', 'rate_limited')
        ->assertSee('You are sending messages too quickly')
        ->assertSet('message', 'second');
});

it('keeps what was typed and offers a retry when the model is unavailable', function () {
    aiFake([[['error' => ['type' => 'api_error', 'message' => 'boom']], 500], [['error' => ['type' => 'api_error', 'message' => 'boom']], 500], [['error' => ['type' => 'api_error', 'message' => 'boom']], 500]]);

    $component = Livewire::test(Chat::class)->set('message', 'hello coach')->call('send')
        ->assertSet('notice.type', 'unavailable')
        ->assertSet('message', 'hello coach')
        ->assertSee('Try again');

    expect(CoachConversation::count())->toBe(0)->and(CoachMessage::count())->toBe(0);

    aiFake([aiText('Hi there!')]);
    $component->call('send')->assertSet('notice', [])->assertSee('Hi there!')->assertSet('message', '');
});

it('validates the message and the attached image', function () {
    Livewire::test(Chat::class)->set('message', '   ')->call('send')->assertHasErrors('message');

    $component = Livewire::test(Chat::class)
        ->set('photo', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->assertSet('photo', null)
        ->assertHasErrors('photo');

    config(['ai.images.max_kb' => 1]);
    $component->set('photo', UploadedFile::fake()->image('big.jpg', 300, 300))
        ->assertSet('photo', null)
        ->assertHasErrors('photo');
});

it('attaches a photo, sends it and shows the chip first', function () {
    aiFake([aiText('Looks like a salad.')]);

    Livewire::test(Chat::class)
        ->set('photo', UploadedFile::fake()->image('meal.jpg', 40, 40))
        ->assertSee('Photo attached')
        ->call('removePhoto')
        ->assertSet('photo', null)
        ->assertDontSee('Photo attached')
        ->set('photo', UploadedFile::fake()->image('meal.jpg', 40, 40))
        ->set('message', 'What is this?')
        ->call('send')
        ->assertSet('photo', null)
        ->assertSee('Looks like a salad.');

    expect(CoachMessage::where('role', 'user')->first()->meta['image'] ?? null)->not->toBeNull();
});

it('deletes a conversation', function () {
    aiFake([aiText('hi')]);
    $component = Livewire::test(Chat::class)->set('message', 'hello')->call('send');
    $conversation = CoachConversation::first();

    Livewire::test(Chat::class)->call('deleteConversation', $conversation->id);

    expect(CoachConversation::count())->toBe(0);
});

it('never exposes the api key in the page or in the livewire payload', function () {
    [$component, $proposal] = coachWithMealProposal($this);

    $html = $this->get('/coach/'.$proposal->conversation_id)->getContent();
    $snapshot = json_encode($component->snapshot ?? []);

    expect($html)->not->toContain(AI_TEST_KEY)->not->toContain('sk-ant')
        ->and($snapshot)->not->toContain(AI_TEST_KEY)
        ->and($component->html())->not->toContain(AI_TEST_KEY);
});
