<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('auth.reset.heading') }}</h1>
        <p class="mt-1.5 text-ink-muted">{{ __('auth.reset.subheading') }}</p>
    </div>

    <x-card padding="lg">
        <form wire:submit="resetPassword" class="space-y-4" novalidate>
            <x-input wire:model="email" type="email" :label="__('auth.email')" autocomplete="email" inputmode="email" required />

            <x-input wire:model="password" type="password" :label="__('auth.password_label')" :hint="__('auth.password_hint')" autocomplete="new-password" required autofocus />

            <x-input wire:model="password_confirmation" type="password" :label="__('auth.password_confirmation')" autocomplete="new-password" required />

            <div class="pt-1">
                <x-button type="submit" loading="resetPassword" size="lg" block>{{ __('auth.reset.submit') }}</x-button>
            </div>
        </form>
    </x-card>
</div>
