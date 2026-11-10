<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('auth.login.heading') }}</h1>
        <p class="mt-1.5 text-ink-muted">{{ __('auth.login.subheading') }}</p>
    </div>

    <x-card padding="lg">
        <form wire:submit="login" class="space-y-4" novalidate>
            <x-input wire:model="email" type="email" :label="__('auth.email')" autocomplete="email" inputmode="email" required autofocus />

            <x-input wire:model="password" type="password" :label="__('auth.password_label')" autocomplete="current-password" required />

            <div class="flex flex-wrap items-center justify-between gap-x-4">
                <x-checkbox wire:model="remember" :label="__('auth.remember')" />
                <a href="{{ route('password.request') }}" wire:navigate class="inline-flex min-h-touch items-center text-sm font-medium text-accent-ink underline-offset-4 hover:underline">
                    {{ __('auth.login.forgot') }}
                </a>
            </div>

            <x-button type="submit" loading="login" size="lg" block>{{ __('auth.login.submit') }}</x-button>
        </form>
    </x-card>

    <p class="mt-6 text-center text-[0.9375rem] text-ink-muted">
        {{ __('auth.login.no_account') }}
        <a href="{{ route('register') }}" wire:navigate class="font-semibold text-accent-ink underline-offset-4 hover:underline">{{ __('auth.login.register') }}</a>
    </p>
</div>
