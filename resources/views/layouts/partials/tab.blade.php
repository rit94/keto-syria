{{-- One item of the mobile tab bar. Expects $item from App\View\Nav. --}}
<a
    href="{{ $item['url'] }}"
    wire:navigate
    @if ($item['active']) aria-current="page" @endif
    @class([
        'flex flex-col items-center justify-center gap-1 text-[0.6875rem] font-medium leading-none transition-colors duration-150',
        'text-accent-ink' => $item['active'],
        'text-ink-muted hover:text-ink' => ! $item['active'],
    ])
>
    <span @class(['flex h-7 w-12 items-center justify-center rounded-full transition-colors duration-150', 'bg-accent-soft' => $item['active']])>
        <x-icon :name="$item['icon']" />
    </span>
    <span>{{ $item['label'] }}</span>
</a>
