{{--
    Radio "choice card" for the onboarding steps.
    Variables: $model (Livewire property), $value, $label, $text (optional), $badge (optional)
--}}
<label
    class="relative flex min-h-touch cursor-pointer items-start gap-3 rounded-card border border-line-strong bg-surface p-3.5 transition-colors duration-150 hover:bg-surface-2 has-checked:border-accent has-checked:bg-accent-soft has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-focus"
>
    <input type="radio" class="peer sr-only" name="{{ $model }}" value="{{ $value }}" wire:model="{{ $model }}">

    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-line-strong text-transparent peer-checked:border-accent peer-checked:bg-accent peer-checked:text-on-accent" aria-hidden="true">
        <x-icon name="check" size="xs" />
    </span>

    <span class="min-w-0 flex-1">
        <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="font-semibold text-ink">{{ $label }}</span>
            @if (! empty($badge))
                <x-badge variant="accent">{{ $badge }}</x-badge>
            @endif
        </span>
        @if (! empty($text))
            <span class="mt-0.5 block text-sm leading-relaxed text-ink-muted">{{ $text }}</span>
        @endif
    </span>
</label>
