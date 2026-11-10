<?php

namespace App\Livewire\Foods;

use App\Enums\FoodCategory;
use App\Models\Food;
use App\Services\Food\FoodSearchService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Browse / search the catalogue, and manage "my ingredients". */
class Index extends Component
{
    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: 'all')]
    public string $tab = 'all';

    public function updatedTab(): void
    {
        if (! in_array($this->tab, ['all', 'mine'], true)) {
            $this->tab = 'all';
        }
    }

    public function render(FoodSearchService $search)
    {
        $user = Auth::user();
        $category = FoodCategory::tryFrom($this->category);
        $mine = $this->tab === 'mine';

        if ($mine) {
            $foods = Food::query()->where('user_id', $user->id)
                ->when($category, fn ($q) => $q->where('category', $category->value))
                ->when(trim($this->query) !== '', function ($q) {
                    $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->query)).'%';
                    $q->where(fn ($w) => $w->where('name_ar', 'like', $term)->orWhere('name_en', 'like', $term));
                })
                ->latest('id')->limit(100)->get();
        } else {
            $foods = $search->search($this->query, $user, 40, $category);
        }

        return view('livewire.foods.index', [
            'foods' => $foods,
            'mine' => $mine,
            'myCount' => Food::query()->where('user_id', $user->id)->count(),
            'categories' => FoodCategory::cases(),
        ])->title(__('foods.title'));
    }
}
