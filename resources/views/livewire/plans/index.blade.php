<x-slot:actions>
    <x-button variant="ghost" square icon="plus" :href="route('plans.create')" navigate aria-label="{{ __('plans.create') }}" />
</x-slot:actions>

<div class="space-y-4">
    @if ($rows->isEmpty())
        <x-empty-state icon="calendar" :title="__('plans.empty_title')" :text="__('plans.empty_text')">
            <x-button icon="plus" :href="route('plans.create')" navigate>{{ __('plans.create') }}</x-button>
        </x-empty-state>
    @else
        <ul class="space-y-3">
            @foreach ($rows as $row)
                @php
                    $plan = $row['plan'];
                @endphp
                <li wire:key="plan-{{ $plan->id }}">
                    <x-card as="a" href="{{ route('plans.show', $plan) }}" wire:navigate interactive padding="sm" @class(['block', 'border-accent' => $row['status'] === 'current'])>
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="min-w-0 text-base font-semibold leading-snug text-ink">{{ $plan->name }}</h2>
                            <x-icon name="chevron-end" size="sm" class="mt-1 text-ink-subtle" />
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-muted">
                            <span>@date($plan->starts_on, 'short') – @date($row['ends'], 'short')</span>
                            <span>·</span>
                            <span><span class="num">@num($plan->days)</span> {{ __('plans.days_unit') }}</span>
                            @if ($row['status'] === 'current')
                                <x-badge variant="accent">{{ __('plans.status.current') }}</x-badge>
                            @elseif ($row['status'] === 'upcoming')
                                <x-badge>{{ __('plans.status.upcoming') }}</x-badge>
                            @endif
                            @if ($plan->origin === 'ai')
                                <x-badge variant="accent" icon="sparkles">{{ __('plans.from_coach') }}</x-badge>
                            @endif
                        </div>
                    </x-card>
                </li>
            @endforeach
        </ul>
    @endif
</div>
