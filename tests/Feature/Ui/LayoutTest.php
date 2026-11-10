<?php

use App\Models\Profile;
use App\Models\User;
use App\View\Nav;
use App\View\Toast;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;

/** A full-page component declared exactly the way CLAUDE.md tells feature agents to. */
class UiFixturePage extends Component
{
    public int $count = 0;

    public bool $open = false;

    public function increment(): void
    {
        $this->count++;
    }

    public function render()
    {
        return view('uitest::page')
            ->title('Fixture title')
            ->layoutData(['back' => '/back-target']);
    }
}

/** Drops the real feature routes so navigation can be tested as features "appear". */
function withoutNavRoutes(): void
{
    $drop = ['today', 'foods.index', 'coach', 'recipes.index', 'plans.index', 'progress', 'profile', 'log.smart'];
    $kept = new RouteCollection;

    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        if (! in_array($route->getName(), $drop, true)) {
            $kept->add($route);
        }
    }

    app('router')->setRoutes($kept);
    app('url')->setRoutes($kept);
    $kept->refreshNameLookups();
}

function registerNavRoutes(array $names): void
{
    withoutNavRoutes();

    foreach ($names as $name) {
        Route::middleware('web')->get('/nav-'.str_replace('.', '-', $name), fn () => view('ui.home'))->name($name);
    }

    app('router')->getRoutes()->refreshNameLookups();
}

beforeEach(function () {
    view()->addNamespace('uitest', __DIR__.'/fixtures');
});

it('renders a full-page Livewire component inside the app layout with title and slots', function () {
    Route::livewire('/fixture', UiFixturePage::class)->middleware('web');

    $this->actingAs(User::factory()->create(['name' => 'Layla']))
        ->get('/fixture')
        ->assertOk()
        ->assertSee('<title>Fixture title · '.__('app.name', [], 'ar').'</title>', false)
        ->assertSee('page-body-content 0')
        ->assertSee('context-slot-content')
        ->assertSee('actions-slot-button')
        ->assertSee('href="/back-target"', false)
        ->assertSee('id="main"', false)
        ->assertSee('Layla');
});

it('keeps Livewire actions working on the fixture page', function () {
    Livewire::test(UiFixturePage::class)
        ->call('increment')
        ->assertSee('page-body-content 1')
        ->set('open', true)
        ->assertSet('open', true);
});

it('hides navigation items whose routes do not exist yet', function () {
    withoutNavRoutes();

    expect(Nav::rail())->toBe([])
        ->and(Nav::tabs())->toBe(['start' => [], 'coach' => null, 'end' => []])
        ->and(Nav::smartLogUrl())->toBeNull();

    $user = User::factory()->create();
    Profile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertDontSee(__('nav.foods', [], 'ar'))
        ->assertDontSee(__('nav.coach', [], 'ar'));
});

it('shows navigation items as their routes appear', function () {
    registerNavRoutes(['foods.index', 'coach', 'recipes.index', 'profile', 'log.smart']);

    expect(array_column(Nav::rail(), 'key'))->toBe(['foods', 'recipes', 'coach', 'profile']);

    $tabs = Nav::tabs();
    expect(array_column($tabs['start'], 'key'))->toBe(['foods'])
        ->and($tabs['coach']['key'])->toBe('coach')
        // slot 4 falls back to recipes, slot 5 to profile
        ->and(array_column($tabs['end'], 'key'))->toBe(['recipes', 'profile'])
        ->and(Nav::overflow())->toBe([])
        ->and(Nav::smartLogUrl())->toBe(route('log.smart'));

    $this->actingAs(User::factory()->create())
        ->get('/nav-foods-index')
        ->assertOk()
        ->assertSee(__('nav.foods', [], 'ar'))
        ->assertSee(__('nav.coach', [], 'ar'))
        ->assertSee(__('nav.log', [], 'ar'))
        ->assertSee('aria-current="page"', false)
        ->assertDontSee(__('nav.plans', [], 'ar'));
});

it('prefers plans and progress in the tab bar and moves the rest to the account menu', function () {
    registerNavRoutes(['today', 'foods.index', 'coach', 'recipes.index', 'plans.index', 'progress', 'profile']);

    $tabs = Nav::tabs();

    expect(array_column($tabs['start'], 'key'))->toBe(['today', 'foods'])
        ->and(array_column($tabs['end'], 'key'))->toBe(['plans', 'progress'])
        ->and(array_column(Nav::overflow(), 'key'))->toBe(['recipes', 'profile']);
});

it('shows flashed toasts and status messages once', function () {
    Toast::success('saved-toast-message');

    $this->get('/login')->assertSee('saved-toast-message');

    $this->withSession(['status' => 'status-flash-message'])
        ->get('/login')
        ->assertSee('status-flash-message');
});

it('renders the guest layout with locale and theme switches', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee(route('locale.switch'), false)
        ->assertSee('KetoTheme', false)
        ->assertSee('name="locale" value="en"', false);
});
