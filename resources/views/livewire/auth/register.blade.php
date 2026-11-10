<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('auth.register.heading') }}</h1>
        <p class="mt-1.5 text-ink-muted">{{ __('auth.register.subheading') }}</p>
    </div>

    <x-card padding="lg">
        <form wire:submit="register" class="space-y-4" novalidate>
            <x-input wire:model="name" :label="__('auth.name')" autocomplete="name" required autofocus />

            <x-input wire:model="email" type="email" :label="__('auth.email')" autocomplete="email" inputmode="email" required />

            <x-input wire:model="password" type="password" :label="__('auth.password_label')" :hint="__('auth.password_hint')" autocomplete="new-password" required />

            <x-input wire:model="password_confirmation" type="password" :label="__('auth.password_confirmation')" autocomplete="new-password" required />

            <div class="pt-1">
                <x-button type="submit" loading="register" size="lg" block>{{ __('auth.register.submit') }}</x-button>
            </div>
        </form>
    </x-card>

    <p class="mt-6 text-center text-[0.9375rem] text-ink-muted">
        {{ __('auth.register.have_account') }}
        <a href="{{ route('login') }}" wire:navigate class="font-semibold text-accent-ink underline-offset-4 hover:underline">{{ __('auth.register.login') }}</a>
    </p>
</div>
