# Keto Syria — project guide

AI-assisted keto tracker for users in Syria. Arabic (RTL, default) + English (LTR).
Stack: Laravel 13, PHP 8.5, Livewire 4, Tailwind 4 (Vite), Pest 4, SQLite in dev/test (migrations must stay MySQL-compatible).

## The one rule

> The AI understands the user. The nutrition engine calculates the food. The database provides the facts.

No nutrition number shown or stored may come from a language model or from ad-hoc arithmetic in a
component/controller. Numbers flow: `Food::per100g()` → `NutritionCalculator` → snapshot on `food_log_entries`.

## Layers (keep them separate)

| Layer | Location | May depend on |
|---|---|---|
| Domain (pure PHP, no Laravel) | `app/Domain/Nutrition`, `app/Domain/Macros` | nothing |
| Models / persistence | `app/Models`, `database/migrations` | Domain value objects |
| Application services | `app/Services/{Food,Logging,Recipes,Plans,Profile,Ai}` | Domain, Models |
| UI | `app/Livewire`, `resources/views` | Services (never raw nutrition math) |
| Routes | `routes/web/<feature>.php` (auto-loaded by `routes/web.php`) | |
| Translations | `lang/{ar,en}/<feature>.php` — one file per feature | |

## Domain contract (already implemented — do not change signatures without need)

- `App\Domain\Nutrition\Nutrients` — immutable; `carbs` is always TOTAL carbs; `fiber`/`saturatedFat` nullable = unknown;
  `netCarbs()`, `scale()`, `plus()`, `sum()`, `missing`, `isComplete()`, `fromLabel(..., carbBasis)`, `toArray()` (full precision), `rounded()` (display only).
- `App\Domain\Nutrition\NutritionCalculator` — `forGrams`, `forIngredients`, `recipe` → `RecipeNutrition`, `forServings`, `rawGramsFromCooked`, `dailyTotals`, `remaining` → `RemainingMacros`.
- `App\Domain\Nutrition\MacroTargets`.
- Precision: floats end-to-end, `decimal(…,3)` columns. Round ONLY in views via `Nutrients::rounded()` / number formatting.

## Data model (see migrations `2026_11_10_*`)

`foods` (per-100 g facts, `user_id` null = catalogue / set = private custom food, `source`, `confidence`, `is_estimate`, `cooked_yield`, `state`, `preparation`, `parent_food_id` for variants), `food_aliases`, `food_units` (household units in grams),
`profiles` (body data + daily targets), `weight_entries`, `recipes`, `recipe_ingredients`, `food_log_entries` (nutrient snapshot), `meal_plans`, `meal_plan_items`.
Enums live in `app/Enums` (string-backed, `label()` reads `lang/*/enums.php`).

## Service contracts (signatures other code relies on)

```php
// app/Services/Food
ArabicNormalizer::normalize(string $text): string                      // static; strips diacritics/tatweel, unifies أإآ→ا, ى→ي, ة→ه, lowercases latin
FoodSearchService->search(string $query, ?User $user, int $limit = 20, ?FoodCategory $category = null): Collection   // of Food, ranked, visibleTo($user)
FoodSearchService->match(string $name, ?User $user, int $limit = 5): Collection   // of ['food' => Food, 'score' => float 0..1], best first; for free-text/AI resolution
CustomFoodService->create(User $user, array $data): Food               // validates; $data may carry 'label_basis' => 'net'
CustomFoodService->update(Food $food, array $data): Food
CustomFoodService->delete(Food $food): void
UnitResolver->toGrams(Food $food, float $quantity, string $unit): float   // 'g','kg','oz', Syrian 'ouqiya' (200 g), or a food_units key; throws App\Services\Food\UnknownUnitException
UnitResolver->unitsFor(Food $food): array                              // [['unit' => 'g', 'label' => 'غ', 'grams' => 1.0], ...]

// app/Services/Logging
FoodLogService->logFood(User $user, Food $food, float $quantity, string $unit, Meal $meal, CarbonInterface $date,
    LogSource $source = LogSource::Manual, ?string $groupId = null, bool $weighedCooked = false): FoodLogEntry
FoodLogService->logRecipe(User $user, Recipe $recipe, float $quantity, string $unit /* 'serving'|'g' */, Meal $meal, CarbonInterface $date, LogSource $source = LogSource::Manual): FoodLogEntry
FoodLogService->logMany(User $user, array $items /* [['food_id'=>int,'quantity'=>float,'unit'=>string,'weighed_cooked'=>bool?], ...] */, Meal $meal, CarbonInterface $date, LogSource $source): Collection  // one transaction, shared group_id
FoodLogService->update(FoodLogEntry $entry, array $changes /* quantity, unit, meal, logged_on */): FoodLogEntry   // recalculates snapshot through the engine
FoodLogService->delete(FoodLogEntry $entry): void
FoodLogService->preview(Food $food, float $quantity, string $unit, bool $weighedCooked = false): Nutrients   // no write
DailySummaryService->forDate(User $user, CarbonInterface $date): DailySummary   // ->date, ->entries (Collection), ->byMeal (meal value => ['entries','totals']), ->totals (Nutrients), ->targets (?MacroTargets), ->remaining (?RemainingMacros)
DailySummaryService->range(User $user, CarbonInterface $from, CarbonInterface $to): Collection   // keyed 'Y-m-d' => Nutrients (days with no entries included as zero)

// app/Services/Recipes, app/Services/Plans
RecipeService->save(User $user, array $data, ?Recipe $recipe = null): Recipe   // $data: name, description, instructions, servings, cooked_weight_g, origin, ingredients[[food_id, quantity, unit]]
RecipeService->nutrition(Recipe $recipe, ?float $servings = null): RecipeNutrition
RecipeService->delete(Recipe $recipe): void
MealPlanService->save(User $user, array $data, ?MealPlan $plan = null): MealPlan   // $data: name, starts_on, days, origin, items[[planned_on, meal, food_id|recipe_id, quantity, unit, note]]
MealPlanService->dayNutrition(MealPlan $plan, CarbonInterface $date): Nutrients
MealPlanService->logItem(MealPlanItem $item): FoodLogEntry              // writes via FoodLogService, sets food_log_entry_id
MealPlanService->delete(MealPlan $plan): void

// app/Domain/Macros + app/Services/Profile
MacroCalculator->calculate(MacroInput $input): MacroResult             // ->targets (MacroTargets), ->bmr, ->tdee, ->calorieAdjustment, ->warnings (list of translation keys), ->requiresMedicalAdvice
ProfileService->save(User $user, array $data, ?array $customTargets = null): Profile
ProfileService->recalculate(Profile $profile): Profile                 // no-op on targets when targets_customized
WeightService->record(User $user, CarbonInterface $date, float $kg, ?string $note = null): WeightEntry   // upsert per day, also updates profile weight when latest
WeightService->trend(User $user, int $days = 90): array
```

Every service method that receives a model owned by a user must be safe against cross-user access: UI code authorises with policies (`app/Policies`) before calling.

## Working rules

- Do not edit files owned by another feature area; add your own route/lang/component files.
- No mock data standing in for real behaviour. Tests may use factories and `Http::fake()`.
- Tests: Pest. Unit tests for pure domain in `tests/Unit`, DB-backed in `tests/Feature/<Area>`. Run only your own files while developing (`php artisan test tests/Feature/<Area>`).
- Arabic first: every user-visible string goes through `__()` with both `ar` and `en` entries. Use logical CSS properties / Tailwind `ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`, `text-start` — never `ml-`/`mr-`/`left-`/`right-` for layout.
- Display numerals as Western digits (0-9) in both locales (Syrian convention).
- Secrets only in `.env` / `config/services.php`; never in views, JS or Livewire public properties.
- Do not run `git commit`.

## UI conventions

Live reference: **`/_ui`** (local + testing only) renders every component in Arabic/RTL and English/LTR. `/_ui?open=modal` / `?open=sheet` renders an overlay already open. Each file in `resources/views/components` starts with a comment documenting its props, slots and usage — read it before using the component.

### Livewire 4 pages (verified against vendor/livewire 4.4 and covered by `tests/Feature/Ui/LayoutTest.php`)

- Class-based components: class in `app/Livewire/<Area>/`, view in `resources/views/livewire/<area>/`. (`make:livewire` defaults to single-file components with a ⚡ prefix; pass `--class` if you use it.)
- Route with `Route::livewire($uri, Component::class)` in your own `routes/web/<feature>.php`. Route parameters are passed to `mount()`.
- The default page layout is `layouts::app` (`resources/views/layouts/app.blade.php`; `layouts::` is Livewire's namespace for `resources/views/layouts`). You get it without declaring anything. Guest pages add `#[Layout('layouts::guest')]` to the class.
- Page title: call `->title(__('...'))` on the view in `render()` (the `#[Title]` attribute only takes a static string, so it cannot be translated). It fills both `<title>` and the top bar heading.
- Extra layout data: `->layoutData(['wide' => true, 'back' => route('foods.index')])` — `wide` = 72rem content column instead of 44rem, `back` = back chevron in the top bar (sub-pages).
- Top-bar slots: declare `<x-slot:context>` (date context line under the title) and `<x-slot:actions>` (icon buttons) in the page view **outside** the single root element.

```php
// routes/web/foods.php
Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::livewire('/foods', App\Livewire\Foods\Index::class)->name('foods.index');
});

// app/Livewire/Foods/Index.php
class Index extends Component
{
    use App\Livewire\Concerns\Toasts;

    public string $query = '';

    public function render()
    {
        return view('livewire.foods.index', [/* data */])->title(__('foods.title'));
    }
}
```

```blade
{{-- resources/views/livewire/foods/index.blade.php --}}
<x-slot:context>@date(now(), 'weekday')</x-slot:context>
<x-slot:actions>
    <x-button variant="ghost" square icon="plus" :href="route('foods.create')" aria-label="{{ __('common.add') }}" />
</x-slot:actions>

<div class="space-y-4">
    <x-input wire:model.live.debounce.300ms="query" type="search" icon="search" :placeholder="__('common.search')" />
    …
</div>
```

- **Feature routes use `['auth', 'onboarded']`.** `auth` sends guests to `route('login')`; `onboarded` (`App\Http\Middleware\EnsureOnboarded`) sends users without a profile to `route('onboarding')` once that route exists. Register the onboarding page itself with `['auth']` only and name it `onboarding`.
- Navigation (`App\View\Nav`) shows an item only when its route name exists: `today`, `foods.index`, `coach`, `recipes.index`, `plans.index`, `progress`, `profile`, and `log.smart` (the "Log a meal" button / top-bar +). Sub-routes named `foods.*`, `log.*` etc. keep the parent item active. `/` (`home`) redirects to `today` once it exists.
- Plain Blade pages (no Livewire) wrap themselves with `@component('layouts::app', ['title' => …]) … @endcomponent`.
- Alpine ships with Livewire (focus/collapse/… plugins included) — do not import it. Shared Alpine data lives in `resources/js/app.js`.
- Blade directives are **not** compiled inside component-tag attributes: write `x-on:click="$dispatch('toast', { message: {{ Js::from($text) }} })"`, not `@js($text)`, on an `<x-…>` tag.
- Tests extend `Tests\TestCase`, which calls `withoutVite()`; no built assets are needed. Run `npm run build` after adding Tailwind classes if you check pages in a browser without `npm run dev`.

### Components (`resources/views/components`, anonymous Blade)

| Component | One-line usage |
|---|---|
| `x-button` | `<x-button wire:click="save">…</x-button>` — `variant` primary/secondary/ghost/danger/danger-ghost/subtle, `size` sm/md/lg, `icon`, `iconEnd`, `href`, `block`, `square`; spinner is automatic for `wire:click`, pass `loading="method"` on submit buttons |
| `x-icon` | `<x-icon name="plus" />` — sizes xs–xl; `chevron-start/end`, `arrow-start/end`, `list`, `logout`, `send` flip in RTL |
| `x-card` | `<x-card :title="…">…</x-card>` — `padding` none/sm/md/lg, `muted`, `interactive`, `as`, slots `actions`, `footer` |
| `x-field` | label + hint + error wrapper for custom controls: `<x-field :label="…" for="id" name="errorKey">…</x-field>` |
| `x-input` | `<x-input wire:model="email" type="email" :label="…" />` — name/id/error derived from `wire:model`; `icon`, `suffix`, `hint`, `optional` |
| `x-textarea` | `<x-textarea wire:model="note" :label="…" rows="3" optional />` |
| `x-select` | `<x-select wire:model="unit" :label="…" :options="$valueToLabel" :placeholder="…" />` (or `<option>`s in the slot) |
| `x-number-stepper` | quantity entry: `<x-number-stepper wire:model.live.debounce.300ms="quantity" :label="…" :step="0.5" :unit="$unitLabel" />` (accepts Arabic-Indic typing, stores Western digits) |
| `x-checkbox` | `<x-checkbox wire:model="remember" :label="…" />` |
| `x-segmented` | single choice of 2–5: `<x-segmented wire:model.live="meal" :label="…" :options="$valueToLabel" />` |
| `x-tabs` / `x-tabs.panel` | Alpine panels, `wire:model.live="tab"`, or link tabs (`['label' =>, 'href' =>, 'active' =>]`) |
| `x-badge` | `<x-badge variant="accent">…</x-badge>`, `<x-badge estimate />` (for `is_estimate`), `<x-badge :confidence="$food->confidence" />` |
| `x-modal` | `<x-modal name="confirm" :title="…">… <x-slot:footer>…</x-slot:footer></x-modal>`; open with `$dispatch('open-modal', 'confirm')`, `$this->dispatch('open-modal', name: 'confirm')` or `wire:model="showConfirm"`; close with `close-modal` |
| `x-bottom-sheet` | same API as `x-modal`; rises from the bottom on phones — the default for pick/add flows on mobile |
| `x-alert` | in-flow message: `<x-alert variant="warning" :title="…">…</x-alert>` (info/success/warning/danger, `dismissible`) |
| `x-toaster` | toast host, already in both layouts — never add it to a page (API below) |
| `x-empty-state` | `<x-empty-state icon="book" :title="…" :text="…"><x-button>…</x-button></x-empty-state>` |
| `x-skeleton` | `<x-skeleton shape="row" />` — text/title/circle/block/row |
| `x-list-row` | `<x-list-row :title="…" :subtitle="…" :value="Format::number($x, 1)" :valueHint="…" :href="…" />`; slots `leading`, `trailing`; stack inside `<x-card padding="none" class="divide-y divide-line">` |
| `x-page-header` | sub-page / section heading: `<x-page-header :title="…" :back="route(…)" level="2">` + `actions` slot |
| `x-stat` | `<x-stat :label="…" :value="82.4" :decimals="1" :unit="…" :delta="-0.6" deltaGood="down" />` |
| `x-macro-ring` | `<x-macro-ring :label="…" macro="carbs" :value="$totals->netCarbs()" :target="$targets?->netCarbs" :left="$remaining?->netCarbs" :unit="__('common.units.g')" />` — over-target state when `limit` (default); `:limit="false"` for goals (protein); `lowerBound` shows "≥" |
| `x-macro-bar` | same props as the ring, linear; `lowerBound` adds "≥" and a hatched end, over-target shows a target tick |
| `x-chart.line` | `<x-chart.line :points="$labelToValue" :target="80" :unit="…" :title="…" />` — nulls are gaps |
| `x-chart.bars` | `<x-chart.bars :points="$labelToValue" :target="20" macro="carbs" :highlight="$todayIndex" :unit="…" />` |
| `x-theme-toggle`, `x-locale-switch`, `x-logo` | shell pieces, already placed by the layouts |

Macro ring/bar and charts only draw numbers they are given: pass engine output (`DailySummary->totals`, `->targets`, `->remaining`; `:lowerBound="$remaining->isLowerBound"` or `! $totals->isComplete()`). Never compute nutrition in a view.

### Tokens (`resources/css/app.css`)

Colours are CSS variables with dark overrides under `.dark`; use the semantic Tailwind utilities — never hex values and never `dark:` colour pairs.

- Surfaces: `bg-canvas` (page), `bg-surface` (cards, sheets), `bg-surface-2` (sunken/hover), `bg-surface-3`, `bg-overlay`
- Text: `text-ink`, `text-ink-muted`, `text-ink-subtle` (all AA on canvas/surface)
- Lines: `border-line` (hairlines), `border-line-strong` (control borders, 3:1)
- Accent (deep olive, the only brand colour): `bg-accent` + `text-on-accent`, `hover:bg-accent-hover`, `bg-accent-soft` + `text-accent-ink`
- Status: `danger|warning|success|info` each with `bg-*-soft` + `text-*-ink`; `bg-danger` + `text-on-danger`
- Macro series: `macro-kcal`, `macro-carbs`, `macro-protein`, `macro-fat`, `macro-fiber` (`bg-`, `text-`, `stroke-`), `bg-track`
- Radius: `rounded-control` (12px inputs/buttons), `rounded-card` (16px), `rounded-sheet` (24px)
- Shadow: `shadow-card`, `shadow-raised`, `shadow-overlay`
- Spacing: `h-touch` / `min-h-touch` / `size-touch` (44px targets), `px-gutter` (page gutter), `h-topbar`, `h-tabbar`, `w-rail`; `max-w-page`, `max-w-page-wide`
- Helpers: `num` (tabular Western digits, bidi-isolated — put it on every number), `control` (input surface), `skeleton`, `pt-safe` / `pb-safe`, `pb-tabbar` and `bottom-tabbar` (clear the mobile tab bar — use `bottom-tabbar` for page-level sticky action bars)
- Page rhythm: `space-y-4` between cards, `space-y-6` between sections; body text 15–16px, never below 12px.
- Motion: only `transition-colors duration-150` style state changes; `prefers-reduced-motion` is honoured globally. Dark mode is `<html class="dark">`, toggled by `window.KetoTheme` (localStorage `theme`).

### RTL rules

- `<html lang dir>` comes from the locale (`App\Http\Middleware\SetLocale`: user's `users.locale` → session → `ar`). Switch with `POST route('locale.switch')` (`locale=ar|en`) — `<x-locale-switch />` does it.
- Logical utilities only (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`, `text-start`, `border-s`, `rounded-s-`). Flex/grid order mirrors automatically; do not reverse it by hand.
- `rtl:` / `ltr:` variants are redefined to follow the nearest `[dir]` ancestor; use them only where a logical utility does not exist (transforms).
- Directional icons use the logical names (`chevron-end` = "forward"); never pick left/right icons by locale.
- Numbers: Western digits, wrapped in `class="num"`. Write value/target pairs inside one `num` span (`12 / 20`) with the unit outside it.
- LTR data in an RTL page (emails, URLs) gets `dir="ltr"`. `x-input` does this for email/password/number/tel/url types.
- Charts mirror in RTL by themselves (time runs right-to-left, value axis on the right).

### Formatting helpers (`App\View\Format`)

- `Format::number($value, $decimals = 0, $fixed = false)` / Blade `@num($value, 1)` — Western digits, thousands separator, trailing zeros trimmed, `null` → "—". Display only: pass engine values through `Nutrients::rounded()` or this, never round before storing.
- `Format::date($date, $preset)` / Blade `@date($date, 'weekday')` — presets `short` (5 كانون الثاني), `medium`, `long`, `weekday`, `day`, `month`, `time`, `numeric`, or any Carbon isoFormat pattern. Arabic uses Levantine month names (`ar_SY`) with Western digits.
- `Format::relativeDay($date)` — اليوم / أمس / غداً, else the weekday preset. `Format::dir()` — `rtl|ltr`. `Format::westernDigits($text)`.
- Shared strings: `lang/*/common.php` (actions, units `common.units.g|kcal|kg`, macro names `common.macros.*`, remaining/over wording). Enum labels: `lang/*/enums.php`. Validation attribute names for new fields: pass them to `validate()` or add to `validation.attributes`.

### Toasts and flash

- In a Livewire component that stays on the page: `use App\Livewire\Concerns\Toasts;` then `$this->toast(__('log.saved'))` or `$this->toast($message, 'error')` (types `success|error|warning|info`). Equivalent to `$this->dispatch('toast', message: …, type: …)`.
- Before a redirect (Livewire or controller): `App\View\Toast::success($message)` (also `::error`, `::warning`, `::info`) or `$this->flashToast($message)`. A plain `session()->flash('status', …)` is shown as a success toast too.
- From Alpine/JS: `$dispatch('toast', { message: '…', type: 'info' })`.
- Use `x-alert` instead when the message must stay visible (form-level errors, medical notices).

### Food picker

Reusable Livewire component `App\Livewire\Foods\Picker` — search (dialect aware), category chips, recent foods, quantity + unit step with live `FoodLogService::preview`.

```blade
<livewire:foods.picker :context="'recipe-ingredient'" :with-quantity="true" :meal="'lunch'" />
```

- Props: `context` (opaque string echoed back), `withQuantity` (default true; false = dispatch on tap with null quantity/unit), `meal` (optional Meal value).
- Emits the Livewire event `food-picked` with named params `context, foodId, quantity, unit, weighedCooked, meal`. Listen with `#[On('food-picked')]` and ignore events whose `context` is not yours. The picker never writes; the listener logs/stores (e.g. `FoodLogService::logFood`).
- Drive it from the parent: `$this->dispatch('food-picker-select', context: 'x', foodId: 5, quantity: 2, unit: 'piece')->to(Picker::class)` jumps to the quantity step; `food-picker-reset` returns to an empty search.
- Put it inside `<x-bottom-sheet>` / `<x-modal>`; see `resources/views/livewire/today/index.blade.php`.
