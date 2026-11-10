<?php

namespace App\Livewire\SmartLog;

use App\Enums\FoodState;
use App\Enums\LogSource;
use App\Enums\Meal;
use App\Livewire\Concerns\Toasts;
use App\Livewire\SmartLog\Support\DraftDay;
use App\Models\Food;
use App\Services\Ai\AnthropicClient;
use App\Services\Ai\Coach\ImageInput;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Exceptions\AiNotConfiguredException;
use App\Services\Ai\Exceptions\AiRateLimitException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\SmartLog\FoodDescriptionParser;
use App\Services\Ai\SmartLog\FoodDraft;
use App\Services\Ai\SmartLog\FoodDraftNotReadyException;
use App\Services\Ai\SmartLog\FoodDraftService;
use App\Services\Ai\Support\AiClock;
use App\Services\Food\UnitResolver;
use App\Services\Logging\LoggingException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Smart food logging: describe a meal (text, voice transcript or photo), review the editable
 * draft, then log it.
 *
 * The language model only extracts what was mentioned (FoodDescriptionParser). Foods, units and
 * every number come from FoodDraftService / the nutrition engine, and nothing is written or
 * reported as logged until FoodDraftService::commit() has returned.
 */
class Capture extends Component
{
    use Toasts;
    use WithFileUploads;

    public const MODES = ['text', 'voice', 'photo'];

    /** Livewire event context used with <livewire:foods.picker>. */
    public const PICKER_CONTEXT = 'smart-log';

    /** text | voice | photo – which input is shown */
    public string $mode = 'text';

    /** the description (text / voice) or an optional note for a photo */
    public string $text = '';

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public int $photoKey = 0;

    /** FoodDraft::toArray() – ids and scalars only; recalculated from the database whenever it is used. */
    public ?array $draft = null;

    /** @var array<string, array{quantity: int|float|string, unit: string, weighed_cooked: bool}> item key => form fields */
    public array $rows = [];

    public string $meal = '';

    public string $date = '';

    /** The user has looked through a draft that needs review (photo, questions, estimates). */
    public bool $acknowledged = false;

    /** @var array<int, string> answers to the clarification questions */
    public array $answers = [];

    /** The input the current draft came from: text | voice | photo (decides the LogSource). */
    #[Locked]
    public string $source = 'text';

    /** Description the current draft was parsed from (answers to questions are appended). */
    #[Locked]
    public string $submittedText = '';

    /** @var list<string> keys of the items that blocked the last commit */
    #[Locked]
    public array $blocked = [];

    /** @var array{type?: string, message?: string, seconds?: int} */
    #[Locked]
    public array $notice = [];

    /** @var array{count: int, meal: string, date: string}|null set only after commit() returned */
    #[Locked]
    public ?array $logged = null;

    #[Locked]
    public ?string $changingKey = null;

    public function mount(): void
    {
        $this->date = AiClock::today()->toDateString();
    }

    // ───────────────────────────── input ─────────────────────────────

    public function updatedMode(): void
    {
        if (! in_array($this->mode, self::MODES, true)) {
            $this->mode = 'text';
        }

        $this->resetErrorBag();
        $this->notice = [];
    }

    public function updatedPhoto(): void
    {
        $this->resetErrorBag('photo');

        if ($this->photo === null) {
            return;
        }

        try {
            ImageInput::assertAcceptable($this->photo);
        } catch (ValidationException $e) {
            $message = $e->errors()['image'][0] ?? __('coach.errors.image_invalid');
            $this->discardPhoto();
            $this->addError('photo', $message);
        }
    }

    public function removePhoto(): void
    {
        $this->resetErrorBag('photo');
        $this->discardPhoto();
    }

    public function analyze(FoodDescriptionParser $parser): void
    {
        $this->resetErrorBag();
        $this->notice = [];
        $this->logged = null;

        $mode = in_array($this->mode, self::MODES, true) ? $this->mode : 'text';
        $user = Auth::user();

        try {
            if ($mode === 'photo') {
                if ($this->photo === null) {
                    $this->addError('photo', __('smart_log.photo.required'));

                    return;
                }

                $draft = $parser->parseImage($user, $this->photo, $this->text);
            } else {
                $draft = $parser->parseText($user, $this->text);
            }
        } catch (Throwable $e) {
            $this->handleAiFailure($e);

            return;
        }

        $this->source = $mode;
        $this->submittedText = trim($this->text);
        $this->answers = [];
        $this->acknowledged = false;
        $this->blocked = [];
        $this->store($draft);

        // The photo is only needed to answer follow-up questions; otherwise drop it right away.
        if ($draft->questions === []) {
            $this->discardPhoto();
        }
    }

    /** Answering a clarification question: parse the description again with the answer added. */
    public function answerQuestion(int $index, FoodDescriptionParser $parser): void
    {
        $draft = $this->draftObject();
        $answer = trim((string) ($this->answers[$index] ?? ''));
        $question = $draft->questions[$index] ?? null;

        if ($draft === null || $question === null || $answer === '') {
            return;
        }

        $this->resetErrorBag();
        $this->notice = [];
        $combined = trim($this->submittedText."\n(".$question.') '.$answer);
        $user = Auth::user();
        $meal = $draft->mealInferred || $draft->meal === null ? null : Meal::tryFrom($draft->meal);
        $date = AiClock::parseDate($draft->date);

        try {
            if ($this->source === 'photo') {
                if ($this->photo === null) {
                    $this->notice = ['type' => 'error', 'message' => __('smart_log.errors.photo_gone')];

                    return;
                }
                $new = $parser->parseImage($user, $this->photo, $combined, $meal, $date);
            } else {
                $new = $parser->parseText($user, $combined, $meal, $date);
            }
        } catch (Throwable $e) {
            $this->handleAiFailure($e);

            return;
        }

        $this->submittedText = $combined;
        $this->answers = [];
        $this->blocked = [];
        $this->store($new);

        if ($new->questions === []) {
            $this->discardPhoto();
        }
    }

    // ───────────────────────────── editing the draft ─────────────────────────────

    /** Quantity, unit or raw/cooked of one row was edited ($name = "<item key>.<field>"). */
    public function updatedRows(mixed $value, string $name): void
    {
        [$key, $field] = array_pad(explode('.', $name, 2), 2, null);

        $changes = match ($field) {
            'quantity' => ['quantity' => $this->number($value)],
            'unit' => ['unit' => is_string($value) ? $value : null],
            'weighed_cooked' => ['weighed_cooked' => (bool) $value],
            default => null,
        };

        if ($key === null || $changes === null || $this->draft === null) {
            return;
        }

        $this->blocked = array_values(array_diff($this->blocked, [$key]));
        $this->apply($this->draftObject()->withItem($key, $changes), keep: [$key, $field]);
    }

    public function updatedMeal(): void
    {
        $meal = Meal::tryFrom($this->meal);

        if ($meal !== null && $this->draft !== null) {
            $this->apply($this->draftObject()->withMeal($meal));
        }
    }

    public function updatedDate(): void
    {
        if ($this->draft !== null) {
            $this->apply($this->draftObject()->withDate($this->date));
            $this->date = $this->draftObject()->date;
        }
    }

    /** One tap on a "did you mean" alternative: it counts as the user's choice. */
    public function chooseCandidate(string $key, int $foodId): void
    {
        $this->blocked = array_values(array_diff($this->blocked, [$key]));
        $this->apply($this->draftObject()->withItem($key, ['food_id' => $foodId]));
    }

    /** "Yes, that is the food" for an ambiguous row. */
    public function confirmFood(string $key): void
    {
        $this->blocked = array_values(array_diff($this->blocked, [$key]));
        $this->apply($this->draftObject()->confirmFood($key));
    }

    public function removeItem(string $key): void
    {
        $this->blocked = array_values(array_diff($this->blocked, [$key]));
        $this->apply($this->draftObject()->withoutItem($key));
    }

    public function openPicker(?string $key = null): void
    {
        $this->changingKey = $key;
        $this->dispatch('food-picker-reset', context: self::PICKER_CONTEXT);
        $this->dispatch('open-modal', name: 'smart-log-picker');
    }

    /** The shared food picker (Livewire event) – either adds a row or replaces the food of one. */
    #[On('food-picked')]
    public function foodPicked(string $context = '', int $foodId = 0, mixed $quantity = null, ?string $unit = null, bool $weighedCooked = false, ?string $meal = null): void
    {
        if ($context !== self::PICKER_CONTEXT || $foodId <= 0 || $this->draft === null) {
            return;
        }

        $draft = $this->draftObject();
        $quantity = $this->number($quantity);

        if ($this->changingKey !== null) {
            $changes = ['food_id' => $foodId];
            if ($quantity !== null && $unit !== null) {
                $changes += ['quantity' => $quantity, 'unit' => $unit];
            }
            $changes['weighed_cooked'] = $weighedCooked;
            $this->blocked = array_values(array_diff($this->blocked, [$this->changingKey]));
            $draft = $draft->withItem($this->changingKey, $changes);
        } else {
            $draft = $draft->withAddedItem($foodId, $quantity, $unit);
            if ($weighedCooked) {
                $draft = $draft->withItem($draft->items[array_key_last($draft->items)]['key'], ['weighed_cooked' => true]);
            }
        }

        $this->changingKey = null;
        $this->apply($draft);
        $this->dispatch('close-modal', name: 'smart-log-picker');
    }

    // ───────────────────────────── commit ─────────────────────────────

    public function commit(FoodDraftService $drafts): void
    {
        $this->notice = [];
        $this->resetErrorBag();

        if ($this->draft === null) {
            return;
        }

        $user = Auth::user();
        $draft = $drafts->recalculate($user, $this->draftObject());
        $this->store($draft);

        // The user must have explicitly confirmed every ambiguous food…
        $ambiguous = array_column(array_filter($draft->items, fn (array $item) => $item['status'] === FoodDraft::AMBIGUOUS), 'key');
        if ($ambiguous !== []) {
            $this->blocked = $ambiguous;
            $this->notice = ['type' => 'error', 'message' => __('smart_log.errors.confirm_ambiguous')];

            return;
        }

        // …and look through a draft that needs review (always the case for photos).
        if ($draft->requiresConfirmation && ! $this->acknowledged) {
            $this->addError('acknowledged', __('smart_log.errors.acknowledge'));

            return;
        }

        $source = match ($this->source) {
            'voice' => LogSource::Voice,
            'photo' => LogSource::Image,
            default => LogSource::Text,
        };

        try {
            $entries = $drafts->commit($user, $draft, $source);
        } catch (FoodDraftNotReadyException $e) {
            $this->store($e->draft);
            $this->blocked = $e->itemKeys;
            $this->notice = ['type' => 'error', 'message' => $e->getMessage()];

            return;
        } catch (LoggingException $e) {
            $this->notice = ['type' => 'error', 'message' => $e->getMessage()];

            return;
        }

        // Only now – after the write has returned – is anything reported as logged.
        $this->logged = ['count' => $entries->count(), 'meal' => $draft->meal ?? '', 'date' => $draft->date];
        $this->toast(__('smart_log.logged_toast'));
        $this->clearInput();
    }

    public function startOver(): void
    {
        $this->clearInput();
        $this->logged = null;
        $this->notice = [];
        $this->resetErrorBag();
    }

    // ───────────────────────────── render ─────────────────────────────

    public function render(AnthropicClient $client, FoodDraftService $drafts)
    {
        $user = Auth::user();
        $draft = null;
        $foods = collect();
        $day = ['remaining' => null, 'has_targets' => false];

        if ($this->draft !== null) {
            // Always derived from the database, never trusted from component state.
            $draft = $drafts->recalculate($user, $this->draftObject());
            $ids = array_values(array_filter(array_column($draft->items, 'food_id')));
            $foods = Food::query()->visibleTo($user)->whereIn('id', $ids)->get()->keyBy('id');
            $day = DraftDay::remainingAfter($user, $draft);
        }

        return view('livewire.smart-log.capture', [
            'configured' => $client->isConfigured(),
            'draftModel' => $draft,
            'foods' => $foods,
            'day' => $day,
            'photoUrl' => $this->photoUrl(),
            'mealOptions' => collect(Meal::cases())->mapWithKeys(fn (Meal $meal) => [$meal->value => $meal->label()])->all(),
            'canCooked' => fn (array $item) => $this->canWeighCooked($item, $foods->get($item['food_id'] ?? 0)),
        ])->title(__('smart_log.title'))->layoutData(['back' => Route::has('today') ? route('today') : null]);
    }

    // ───────────────────────────── internals ─────────────────────────────

    private function draftObject(): FoodDraft
    {
        return FoodDraft::fromArray($this->draft ?? []);
    }

    /** Recalculate after an edit and keep the form fields in step. */
    private function apply(FoodDraft $draft, array $keep = []): void
    {
        $this->notice = [];
        $this->store(app(FoodDraftService::class)->recalculate(Auth::user(), $draft), $keep);
    }

    /** @param  array{0?: string, 1?: ?string}  $keep  [item key, field] the user is typing in right now */
    private function store(FoodDraft $draft, array $keep = []): void
    {
        $this->draft = $draft->toArray();
        $this->meal = $draft->meal ?? '';
        $this->date = $draft->date;

        $rows = [];
        foreach ($draft->items as $item) {
            $existing = $this->rows[$item['key']] ?? null;
            $typing = ($keep[0] ?? null) === $item['key'] ? ($keep[1] ?? null) : null;

            $rows[$item['key']] = [
                'quantity' => $typing === 'quantity' && $existing !== null ? $existing['quantity'] : $this->tidy($item['quantity']),
                'unit' => (string) ($item['unit'] ?? ''),
                'weighed_cooked' => (bool) $item['weighed_cooked'],
            ];
        }
        $this->rows = $rows;
    }

    private function clearInput(): void
    {
        $this->draft = null;
        $this->rows = [];
        $this->answers = [];
        $this->blocked = [];
        $this->acknowledged = false;
        $this->submittedText = '';
        $this->text = '';
        $this->changingKey = null;
        $this->date = AiClock::today()->toDateString();
        $this->meal = '';
        $this->discardPhoto();
    }

    private function handleAiFailure(Throwable $e): void
    {
        if ($e instanceof ValidationException) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'image' ? 'photo' : $field, $messages[0]);
            }

            return;
        }

        $this->notice = match (true) {
            $e instanceof AiNotConfiguredException => ['type' => 'not_configured', 'message' => $e->getMessage()],
            $e instanceof AiRateLimitException => ['type' => 'rate_limited', 'message' => $e->getMessage(), 'seconds' => $e->retryAfterSeconds],
            $e instanceof AiUnavailableException => ['type' => 'unavailable', 'message' => $e->getMessage()],
            $e instanceof AiException => ['type' => 'error', 'message' => $e->getMessage()],
            default => throw $e,
        };
    }

    /** "Weighed after cooking" only means something for a mass of a raw food that has a cooked yield. */
    private function canWeighCooked(array $item, ?Food $food): bool
    {
        return $food !== null
            && $food->state === FoodState::Raw
            && $food->cooked_yield !== null
            && isset(UnitResolver::UNIVERSAL[(string) ($item['unit'] ?? '')]);
    }

    private function discardPhoto(): void
    {
        try {
            $this->photo?->delete();
        } catch (Throwable) {
            // temporary files are purged by Livewire anyway
        }

        $this->photo = null;
        $this->photoKey++;
    }

    private function photoUrl(): ?string
    {
        try {
            return $this->photo?->isPreviewable() ? $this->photo->temporaryUrl() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function number(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = strtr(trim($value), [
                "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3', "\u{0664}" => '4',
                "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7', "\u{0668}" => '8', "\u{0669}" => '9',
                "\u{066B}" => '.', "\u{060C}" => '.', ',' => '.',
            ]);
        }

        return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
    }

    private function tidy(mixed $value): int|float|string
    {
        if (! is_numeric($value)) {
            return '';
        }

        $value = (float) $value;

        return floor($value) === $value ? (int) $value : round($value, 3);
    }
}
