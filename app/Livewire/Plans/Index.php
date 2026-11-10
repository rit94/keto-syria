<?php

namespace App\Livewire\Plans;

use App\Models\MealPlan;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $today = today();

        $plans = MealPlan::query()
            ->where('user_id', auth()->id())
            ->withCount('items')
            ->orderByDesc('starts_on')
            ->get()
            ->map(function (MealPlan $plan) use ($today) {
                $ends = $plan->starts_on->copy()->addDays($plan->days - 1);

                return [
                    'plan' => $plan,
                    'ends' => $ends,
                    'status' => match (true) {
                        $today->betweenIncluded($plan->starts_on, $ends) => 'current',
                        $plan->starts_on->greaterThan($today) => 'upcoming',
                        default => 'past',
                    },
                ];
            });

        // current first, then upcoming (soonest first), then past (latest first)
        $sorted = $plans->sortBy(fn (array $row) => [
            ['current' => 0, 'upcoming' => 1, 'past' => 2][$row['status']],
            $row['status'] === 'upcoming' ? $row['plan']->starts_on->timestamp : -$row['plan']->starts_on->timestamp,
        ])->values();

        return view('livewire.plans.index', ['rows' => $sorted])->title(__('plans.title'));
    }
}
