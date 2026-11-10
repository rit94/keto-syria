<?php

namespace App\Livewire\Progress;

use App\Domain\Macros\MacroCalculator;
use App\Domain\Nutrition\Nutrients;
use App\Livewire\Concerns\Toasts;
use App\Models\User;
use App\Models\WeightEntry;
use App\Services\Profile\NutritionHistory;
use App\Services\Profile\WeightService;
use App\View\Format;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Weight trend + nutrition history. All figures are WeightService / NutritionHistory output.
 */
class Index extends Component
{
    use AuthorizesRequests, Toasts;

    public const array WEIGHT_RANGES = [30, 90, 365];

    public const array HISTORY_RANGES = [7, 30];

    public const int LIST_PAGE = 7;

    #[Url(except: 90)]
    public int $range = 90;

    #[Url(except: 7)]
    public int $history = 7;

    public int $listLimit = self::LIST_PAGE;

    // Weigh-in sheet
    public string $measured_on = '';

    /** @var string|int|float|null */
    public $weight_kg = null;

    public string $note = '';

    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->range = in_array($this->range, self::WEIGHT_RANGES, true) ? $this->range : 90;
        $this->history = in_array($this->history, self::HISTORY_RANGES, true) ? $this->history : 7;
        $this->measured_on = Carbon::today()->toDateString();
    }

    public function updatedRange(): void
    {
        $this->range = in_array((int) $this->range, self::WEIGHT_RANGES, true) ? (int) $this->range : 90;
    }

    public function updatedHistory(): void
    {
        $this->history = in_array((int) $this->history, self::HISTORY_RANGES, true) ? (int) $this->history : 7;
    }

    public function addWeight(): void
    {
        $this->resetErrorBag();
        $this->measured_on = Carbon::today()->toDateString();
        $this->weight_kg = $this->user()->weightEntries()->whereDate('measured_on', $this->measured_on)->value('weight_kg');
        $this->note = '';

        $this->dispatch('open-modal', name: 'weight-entry');
    }

    public function editWeight(int $id): void
    {
        $entry = WeightEntry::findOrFail($id);
        $this->authorize('update', $entry);

        $this->resetErrorBag();
        $this->measured_on = $entry->measured_on->toDateString();
        $this->weight_kg = $entry->weight_kg;
        $this->note = (string) $entry->note;

        $this->dispatch('open-modal', name: 'weight-entry');
    }

    public function saveWeight(WeightService $weights): void
    {
        $this->authorize('create', WeightEntry::class);

        $data = $this->validate(
            [
                'measured_on' => ['required', 'date'],
                'weight_kg' => ['required', 'numeric'],
                'note' => ['nullable', 'string', 'max:255'],
            ],
            attributes: [
                'measured_on' => __('progress.weight.date'),
                'weight_kg' => __('macros.fields.weight_kg'),
                'note' => __('progress.weight.note'),
            ],
        );

        // Range and future-date rules (and their messages) are the service's.
        $weights->record($this->user(), Carbon::parse($data['measured_on']), (float) $data['weight_kg'], $data['note'] ?? null);

        $this->dispatch('close-modal', name: 'weight-entry');
        $this->toast(__('progress.weight.saved'));
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', WeightEntry::findOrFail($id));

        $this->deletingId = $id;
        $this->dispatch('open-modal', name: 'delete-weight');
    }

    public function deleteWeight(WeightService $weights): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $entry = WeightEntry::findOrFail($this->deletingId);
        $this->authorize('delete', $entry);

        $weights->delete($entry);

        $this->deletingId = null;
        $this->dispatch('close-modal', name: 'delete-weight');
        $this->toast(__('progress.weight.deleted'));
    }

    public function showMore(): void
    {
        $this->listLimit += self::LIST_PAGE * 2;
    }

    public function render(WeightService $weights, NutritionHistory $nutrition)
    {
        $user = $this->user();
        $trend = $weights->trend($user, $this->range);
        $hist = $nutrition->forUser($user, $this->history);

        $entries = $user->weightEntries()->orderByDesc('measured_on')->limit($this->listLimit + 1)->get();

        $dayLabel = $this->history <= 7 ? 'dd' : 'D/M';
        $bars = fn (callable $pick) => $hist['days']
            ->map(fn (?Nutrients $day, string $date) => [
                'label' => Format::date($date, $dayLabel),
                'value' => $day === null ? null : $pick($day),
            ])
            ->values()
            ->all();

        return view('livewire.progress.index', [
            'trend' => $trend,
            'weightPoints' => array_map(fn (array $entry) => [
                'label' => Format::date($entry['date'], 'D/M'),
                'value' => $entry['kg'],
            ], $trend['entries']),
            'tooFast' => $this->losingTooFast($trend),
            'entries' => $entries->take($this->listLimit),
            'hasMore' => $entries->count() > $this->listLimit,
            'deleting' => $this->deletingId ? $entries->firstWhere('id', $this->deletingId) : null,
            'hist' => $hist,
            'netCarbBars' => $bars(fn (Nutrients $day) => $day->netCarbs()),
            'calorieBars' => $bars(fn (Nutrients $day) => $day->calories),
            'rangeOptions' => collect(self::WEIGHT_RANGES)->mapWithKeys(fn (int $days) => [$days => __('progress.range.'.$days)])->all(),
            'historyOptions' => collect(self::HISTORY_RANGES)->mapWithKeys(fn (int $days) => [$days => __('progress.range.'.$days)])->all(),
            'today' => Carbon::today()->toDateString(),
        ])->title(__('progress.title'));
    }

    /**
     * True when the trend shows a loss faster than the calculator's own ceiling
     * (MacroCalculator::MAX_WEEKLY_LOSS_SHARE of body weight per week).
     *
     * @param  array{weekly_rate_kg: ?float, current_kg: ?float}  $trend
     */
    private function losingTooFast(array $trend): bool
    {
        if ($trend['weekly_rate_kg'] === null || $trend['current_kg'] === null) {
            return false;
        }

        return -$trend['weekly_rate_kg'] > $trend['current_kg'] * MacroCalculator::MAX_WEEKLY_LOSS_SHARE;
    }

    private function user(): User
    {
        return Auth::user();
    }
}
