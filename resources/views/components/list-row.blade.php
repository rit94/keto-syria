{{--
    Row in a list (food result, log entry, settings item). Min height 56 px.
    Stack rows inside <x-card padding="none"> with class="divide-y divide-line", or in a <ul>.

    Props
      title     string        primary text (required unless the default slot is used)
      subtitle  string|null   secondary text under the title
      icon      string|null   leading icon in a soft circle (or use the `leading` slot)
      value     string|null   trailing value text (pre-formatted, e.g. Format::number(...))
      valueHint string|null   small text under the value (e.g. unit)
      href      string|null   make the whole row a link (adds a chevron)
      as        string|null   force the tag (div|li|button|a); default: a when href, else div
      chevron   bool|null     show the trailing chevron; defaults to true for links
      navigate  bool          add wire:navigate to links (default true)

    Slots
      leading   custom leading visual (replaces icon)
      default   extra content under the subtitle (badges…)
      trailing  custom trailing content (replaces value; e.g. buttons)

    Usage
      <x-list-row :title="$food->displayName()" :subtitle="$food->category->label()"
                  :value="App\View\Format::number($n->netCarbs(), 1)" :valueHint="__('common.units.g')"
                  :href="route('foods.show', $food)" />
      <x-list-row as="button" wire:click="pick({{ $food->id }})" icon="plus" :title="$food->displayName()" />
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'value' => null,
    'valueHint' => null,
    'href' => null,
    'as' => null,
    'chevron' => null,
    'navigate' => true,
])

@php
    $tag = $as ?? ($href ? 'a' : 'div');
    $interactive = in_array($tag, ['a', 'button'], true);
    $chevron ??= (bool) $href;
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @if ($navigate) wire:navigate @endif @endif
    @if ($tag === 'button') type="button" @endif
    {{ $attributes->class([
        'flex min-h-14 w-full items-center gap-3 px-4 py-2.5 text-start',
        'transition-colors duration-150 hover:bg-surface-2 focus-visible:-outline-offset-2' => $interactive,
    ]) }}
>
    @if (isset($leading))
        <div class="shrink-0">{{ $leading }}</div>
    @elseif ($icon)
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface-2 text-ink-muted">
            <x-icon :name="$icon" />
        </span>
    @endif

    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="truncate text-[0.9375rem] font-medium leading-snug text-ink">{{ $title }}</p>
        @endif
        @if ($subtitle)
            <p class="truncate text-sm leading-snug text-ink-muted">{{ $subtitle }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div class="mt-1 flex flex-wrap items-center gap-1.5">{{ $slot }}</div>
        @endif
    </div>

    @if (isset($trailing))
        <div class="flex shrink-0 items-center gap-1">{{ $trailing }}</div>
    @elseif ($value !== null)
        <div class="shrink-0 text-end">
            <p class="num text-[0.9375rem] font-semibold text-ink">{{ $value }}</p>
            @if ($valueHint)
                <p class="text-xs text-ink-subtle">{{ $valueHint }}</p>
            @endif
        </div>
    @endif

    @if ($chevron)
        <x-icon name="chevron-end" size="sm" class="text-ink-subtle" />
    @endif
</{{ $tag }}>
