{{--
    Empty state for lists and pages with no data yet.

    Props
      icon   string        icon name (default list)
      title  string        short statement (required)
      text   string|null   one sentence on what to do next
      compact bool         smaller padding, for use inside cards

    Slot
      default — the action(s), usually one primary button

    Usage
      <x-empty-state icon="book" :title="__('recipes.empty_title')" :text="__('recipes.empty_text')">
          <x-button icon="plus" :href="route('recipes.create')">{{ __('recipes.create') }}</x-button>
      </x-empty-state>
--}}
@props(['icon' => 'list', 'title', 'text' => null, 'compact' => false])

<div {{ $attributes->class(['flex flex-col items-center text-center', 'px-4 py-6' => $compact, 'px-6 py-12' => ! $compact]) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-surface-2 text-ink-muted">
        <x-icon :name="$icon" size="lg" />
    </span>
    <h3 class="mt-4 text-base font-semibold text-ink">{{ $title }}</h3>
    @if ($text)
        <p class="mt-1 max-w-xs text-[0.9375rem] text-ink-muted">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
