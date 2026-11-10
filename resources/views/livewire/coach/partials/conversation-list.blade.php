{{-- Conversation list. Needs: $conversations, $current (?CoachConversation). Delete uses the Alpine `deleteId` of chat.blade.php. --}}
@if ($conversations->isEmpty())
    <p class="px-1 py-2 text-sm text-ink-muted">{{ __('coach_ui.no_conversations') }}</p>
@else
    <ul class="space-y-1">
        @foreach ($conversations as $item)
            <li wire:key="conv-{{ $item->id }}" class="group relative">
                <a
                    href="{{ route('coach.show', $item) }}"
                    wire:navigate
                    @if ($current?->id === $item->id) aria-current="page" @endif
                    @class([
                        'block min-h-touch rounded-control py-2 ps-3 pe-11 transition-colors duration-150',
                        'bg-accent-soft text-accent-ink' => $current?->id === $item->id,
                        'text-ink hover:bg-surface-2' => $current?->id !== $item->id,
                    ])
                    x-on:click="$dispatch('close-modal', 'coach-conversations')"
                >
                    <span class="block truncate text-[0.9375rem] font-medium">{{ $item->title ?: __('coach_ui.untitled') }}</span>
                    <span class="block text-xs text-ink-subtle">{{ \App\View\Format::relativeDay($item->updated_at) }}</span>
                </a>
                <button
                    type="button"
                    x-on:click="deleteId = {{ $item->id }}; $dispatch('open-modal', 'delete-conversation')"
                    class="absolute end-0 top-0 flex size-touch items-center justify-center rounded-full text-ink-subtle transition-colors duration-150 hover:bg-danger-soft hover:text-danger-ink focus-visible:text-danger-ink"
                    aria-label="{{ __('coach_ui.delete.action') }}: {{ $item->title ?: __('coach_ui.untitled') }}"
                >
                    <x-icon name="trash" size="sm" />
                </button>
            </li>
        @endforeach
    </ul>
@endif
