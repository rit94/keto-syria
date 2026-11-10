<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('auth.forgot.heading') }}</h1>
        <p class="mt-1.5 text-ink-muted">{{ __('auth.forgot.subheading') }}</p>
    </div>

    <x-card padding="lg">
        <form wire:submit="sendResetLink" class="space-y-4" novalidate>
            @if ($status)
                <x-alert variant="success">{{ $status }}</x-alert>
            @endif

            <x-input wire:model="email" type="email" :label="__('auth.email')" autocomplete="email" inputmode="email" required autofocus />

            <x-button type="submit" loading="sendResetLink" size="lg" block>{{ __('auth.forgot.submit') }}</x-button>
        </form>
    </x-card>

    <p class="mt-6 text-center">
        <a href="{{ route('login') }}" wire:navigate class="inline-flex min-h-touch items-center gap-1.5 text-[0.9375rem] font-semibold text-accent-ink underline-offset-4 hover:underline">
            <x-icon name="arrow-start" size="sm" />
            {{ __('auth.forgot.back') }}
        </a>
    </p>
</div>
