<?php

use App\Enums\FoodCategory;
use App\Models\Food;
use App\Models\User;
use App\Services\Food\CustomFoodService;
use App\Services\Food\FoodSearchService;
use Database\Seeders\FoodCatalogueSeeder;

beforeEach(function () {
    $this->seed(FoodCatalogueSeeder::class);
    $this->search = app(FoodSearchService::class);
});

function foodSlugs(iterable $foods): array
{
    return collect($foods)->pluck('slug')->all();
}

describe('search ranking', function () {
    it('puts the exact alias first, then names that start with the query', function () {
        $results = foodSlugs($this->search->search('بيض', null, 10));

        expect($results[0])->toBe('egg-whole-raw')
            ->and(array_slice($results, 0, 6))->toContain('egg-boiled', 'egg-fried');
    });

    it('ranks exact > prefix > token-contains', function () {
        // "لبن" is exactly yogurt, a prefix of "لبنة…", and a token inside nothing else.
        $results = foodSlugs($this->search->search('لبن', null, 10));

        expect($results[0])->toBe('yogurt-whole')
            ->and($results)->toContain('labneh', 'labneh-balls-oil')
            ->and(array_search('yogurt-whole', $results))->toBeLessThan(array_search('labneh', $results));

        // "فروج" is exactly the whole chicken; cuts only contain the token.
        $chicken = $this->search->match('فروج', null, 40);
        $scores = $chicken->mapWithKeys(fn (array $m) => [$m['food']->slug => $m['score']]);

        expect($chicken->first()['food']->slug)->toBe('chicken-whole-raw')
            ->and($scores['chicken-whole-raw'])->toBe(1.0)
            ->and($scores['chicken-whole-roasted'])->toBeGreaterThan($scores['chicken-thigh-skin-raw'])
            ->and($scores['chicken-thigh-skin-raw'])->toBeLessThanOrEqual(0.8);
    });

    it('lists all variants of canned tuna for the dialect word', function () {
        $results = foodSlugs($this->search->search('طون', null, 5));

        expect($results[0])->toBe('tuna-oil-drained')
            ->and($results)->toContain('tuna-oil-undrained', 'tuna-water-drained');
    });

    it('prefers the base food over its variants on equal scores', function () {
        $results = foodSlugs($this->search->search('لحمة كباب', null, 5));

        expect($results[0])->toBe('kebab-mince-regular')
            ->and($results)->toContain('kebab-mince-lean', 'kebab-mince-fatty');
    });

    it('matches multi-word queries in any word order', function () {
        expect(foodSlugs($this->search->search('فروج صدر', null, 3)))->toContain('chicken-breast-raw')
            ->and(foodSlugs($this->search->search('عضم مرق', null, 3)))->toContain('bone-broth');
    });

    it('respects the limit and the category filter', function () {
        $cheeses = $this->search->search('جبنة', null, 50, FoodCategory::Cheese);

        expect($cheeses)->not->toBeEmpty()
            ->and($cheeses->every(fn (Food $f) => $f->category === FoodCategory::Cheese))->toBeTrue()
            ->and($this->search->search('جبنة', null, 3))->toHaveCount(3)
            ->and($this->search->search('حلوم', null, 20, FoodCategory::Meat))->toBeEmpty();
    });

    it('eager-loads household units', function () {
        $food = $this->search->search('حلوم', null, 1)->first();

        expect($food->relationLoaded('units'))->toBeTrue()
            ->and($food->units->pluck('unit')->all())->toContain('slice');
    });
});

describe('dialect, spelling and typos', function () {
    it('ignores the definite article', function (string $query, string $slug) {
        expect(foodSlugs($this->search->search($query, null, 3))[0])->toBe($slug);
    })->with([
        ['الزهرة', 'cauliflower-raw'],
        ['الحلوم', 'halloumi'],
        ['اللبنة', 'labneh'],
        ['دهنة مرق عضم', 'bone-broth-fat'],
        ['الطحينة', 'tahini'],
    ]);

    it('treats common spelling variants as the same word', function (string $query, string $slug) {
        expect(foodSlugs($this->search->search($query, null, 3))[0])->toBe($slug);
    })->with([
        'ta marbuta as ha' => ['لبنه', 'labneh'],
        'ha as ta marbuta' => ['زهرة', 'cauliflower-raw'],
        'tashkeel' => ['لَبْنَة', 'labneh'],
        'tatweel' => ['حـلـوم', 'halloumi'],
        'hamza dropped' => ['افوكادو', 'avocado'],
        'hamza below' => ['اريثريتول', 'erythritol'],
        'alef maqsura' => ['كوسى', 'zucchini-raw'],
        'dialect د for ذ' => ['بادنجان', 'eggplant-raw'],
        'standard ث for dialect ت' => ['ثوم', 'garlic'],
        'standard word' => ['قرنبيط', 'cauliflower-raw'],
        'standard tuna' => ['تونة', 'tuna-oil-drained'],
        'egyptian tomato' => ['طماطم', 'tomato'],
        'glottal qaf' => ['أهوة', 'coffee'],
    ]);

    it('finds foods by Latin transliteration', function (string $query, string $slug) {
        expect(foodSlugs($this->search->search($query, null, 3))[0])->toBe($slug);
    })->with([
        ['halloumi', 'halloumi'],
        ['Labneh', 'labneh'],
        ['makdous', 'makdous-drained'],
        ['TAHINI', 'tahini'],
        ['shanklish', 'shanklish'],
        ['chicken breast', 'chicken-breast-raw'],
        ['olive oil', 'olive-oil'],
        ['mate', 'mate'],
    ]);

    it('tolerates typos through fuzzy matching', function (string $query, string $slug) {
        expect(foodSlugs($this->search->search($query, null, 5)))->toContain($slug);
    })->with([
        ['حلوومي', 'halloumi'],
        ['مكدووس', 'makdous-drained'],
        ['برووكلي', 'broccoli'],
        ['شنكلييش', 'shanklish'],
        ['افوكادوو', 'avocado'],
        ['halumi', 'halloumi'],
        ['chiken breast', 'chicken-breast-raw'],
        ['جبنة عكاووي', 'akkawi'],
    ]);

    it('ranks fuzzy hits below real matches', function () {
        $typo = $this->search->match('مكدووس', null, 1)->first();
        $exact = $this->search->match('مكدوس', null, 1)->first();

        expect($exact['score'])->toBe(1.0)
            ->and($typo['food']->slug)->toBe('makdous-drained')
            ->and($typo['score'])->toBeLessThan(0.6)->toBeGreaterThan(0.3);
    });

    it('returns nothing for text that resembles no food', function () {
        expect($this->search->search('qwertyuiop', null))->toBeEmpty()
            ->and($this->search->search('ضصثقفغعهخ', null))->toBeEmpty();
    });
});

describe('empty query', function () {
    it('returns popular foods in a stable order', function () {
        $results = foodSlugs($this->search->search('', null, 8));

        expect($results)->toBe(array_slice(FoodSearchService::POPULAR, 0, 8))
            ->and(foodSlugs($this->search->search('   ', null, 8)))->toBe($results);
    });

    it('only lists popular slugs that exist in the catalogue', function () {
        expect(Food::whereIn('slug', FoodSearchService::POPULAR)->count())->toBe(count(FoodSearchService::POPULAR));
    });

    it('honours the category filter and the limit', function () {
        $fats = $this->search->search('', null, 4, FoodCategory::Fats);

        expect($fats)->toHaveCount(4)
            ->and($fats->every(fn (Food $f) => $f->category === FoodCategory::Fats))->toBeTrue()
            ->and($fats->first()->slug)->toBe('olive-oil');
    });

    it('shows the user\'s own foods first', function () {
        $user = User::factory()->create();
        $mine = app(CustomFoodService::class)->create($user, ['name_ar' => 'خبز كيتو بيتي', 'calories' => 250, 'protein_g' => 20, 'carbs_g' => 8, 'fiber_g' => 5, 'fat_g' => 15]);

        expect($this->search->search('', $user, 8)->first()->id)->toBe($mine->id)
            ->and($this->search->search('', null, 200)->pluck('id')->all())->not->toContain($mine->id);
    });
});

describe('match()', function () {
    it('scores 1.0 only for an exact normalized name or alias', function () {
        $exact = $this->search->match('حلّوم', null);
        $article = $this->search->match('الحلوم', null);
        $longer = $this->search->match('حلوم مشوي عالفحم', null);

        expect($exact->first()['food']->slug)->toBe('halloumi')
            ->and($exact->first()['score'])->toBe(1.0)
            ->and($article->first()['food']->slug)->toBe('halloumi')
            ->and($article->first()['score'])->toBe(0.97)
            ->and($longer->first()['score'])->toBeLessThan(0.97);

        expect($this->search->match('Egg, whole, raw', null)->first()['score'])->toBe(1.0)
            ->and($this->search->match('بيض كامل (ني)', null)->first()['score'])->toBe(1.0);
    });

    it('returns candidates best first, limited, with scores between 0 and 1', function () {
        $matches = $this->search->match('جبنة', null, 4);
        $scores = $matches->pluck('score')->all();
        $sorted = $scores;
        rsort($sorted);

        expect($matches)->toHaveCount(4)
            ->and($scores)->toBe($sorted)
            ->and(min($scores))->toBeGreaterThan(0.0)
            ->and(max($scores))->toBeLessThanOrEqual(1.0)
            ->and($matches->first()['food'])->toBeInstanceOf(Food::class);
    });

    it('returns an empty collection for blank or unknown text', function () {
        expect($this->search->match('', null))->toBeEmpty()
            ->and($this->search->match('zzzzzzzzzz', null))->toBeEmpty();
    });

    it('resolves a partially matching free-text phrase to the closest food', function () {
        $matches = $this->search->match('صدر دجاج مشوي', null, 3);

        expect($matches->first()['food']->slug)->toBe('chicken-breast-cooked')
            ->and($matches->first()['score'])->toBe(1.0);

        expect(foodSlugs($this->search->match('بيض بلدي مسلوق', null, 3)->pluck('food')))->toContain('egg-boiled');
    });
});

describe('user isolation', function () {
    beforeEach(function () {
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->custom = app(CustomFoodService::class)->create($this->alice, [
            'name_ar' => 'جبنة أم خالد', 'name_en' => 'Um Khaled cheese', 'category' => 'cheese',
            'calories' => 300, 'protein_g' => 20, 'carbs_g' => 2, 'fat_g' => 24, 'aliases' => ['جبنة الضيعة'],
        ]);
    });

    it('lets the owner find a custom food by name, English name and alias', function () {
        expect($this->search->search('جبنة أم خالد', $this->alice, 5)->first()->id)->toBe($this->custom->id)
            ->and($this->search->search('um khaled', $this->alice, 5)->first()->id)->toBe($this->custom->id)
            ->and($this->search->search('جبنة الضيعة', $this->alice, 5)->first()->id)->toBe($this->custom->id)
            ->and($this->search->match('جبنة ام خالد', $this->alice)->first()['score'])->toBe(1.0);
    });

    it('never shows a custom food to another user or to a guest', function () {
        foreach (['جبنة أم خالد', 'um khaled', 'جبنة الضيعة', 'جبنة', 'خالد'] as $query) {
            expect($this->search->search($query, $this->bob, 200)->pluck('id')->all())->not->toContain($this->custom->id)
                ->and($this->search->search($query, null, 200)->pluck('id')->all())->not->toContain($this->custom->id)
                ->and($this->search->match($query, $this->bob, 200)->pluck('food.id')->all())->not->toContain($this->custom->id);
        }

        expect($this->search->search('', $this->bob, 500)->pluck('id')->all())->not->toContain($this->custom->id);
    });

    it('mixes catalogue and own foods for the owner', function () {
        $results = $this->search->search('جبنة', $this->alice, 50);

        expect($results->pluck('id')->all())->toContain($this->custom->id)
            ->and($results->pluck('slug')->filter()->all())->toContain('white-cheese-baladi', 'halloumi');
    });

    it('applies the category filter to custom foods too', function () {
        expect($this->search->search('خالد', $this->alice, 10, FoodCategory::Cheese)->pluck('id')->all())->toContain($this->custom->id)
            ->and($this->search->search('خالد', $this->alice, 10, FoodCategory::Meat))->toBeEmpty();
    });
});
