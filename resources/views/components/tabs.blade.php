{{--
    Underline tabs. Three ways to use it:

      1. Client-side panels (Alpine):
           <x-tabs :tabs="['macros' => __('x.macros'), 'weight' => __('x.weight')]" active="macros">
               <x-tabs.panel name="macros">…</x-tabs.panel>
               <x-tabs.panel name="weight">…</x-tabs.panel>
           </x-tabs>

      2. Livewire state:  <x-tabs wire:model.live="tab" :tabs="[…]" />   then  @if ($tab === 'weight') … @endif

      3. Links between pages — give each tab an array with `href` (and `active`):
           <x-tabs :tabs="[
               ['label' => __('nav.recipes'), 'href' => route('recipes.index'), 'active' => true],
               ['label' => __('nav.plans'),   'href' => route('plans.index')],
           ]" />

    Props
      tabs    array         key => label, or key => ['label' => …, 'href' => …, 'active' => bool, 'count' => int]
      active  string|null   initially active key (modes 1–2); defaults to the first tab
      label   string|null   accessible name of the tab list
--}}
@props(['tabs', 'active' => null, 'label' => null])

@php
    $active ??= array_key_first($tabs);
    $tabClasses = 'relative inline-flex min-h-touch shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-3 text-[0.9375rem] font-medium transition-colors duration-150 -mb-px';
    $on = 'border-accent text-ink';
    $off = 'border-transparent text-ink-muted hover:text-ink';
@endphp

<div
    x-data="{ active: @js((string) $active) }"
    x-modelable="active"
    {{ $attributes }}
>
    <div
        role="tablist"
        @if ($label) aria-label="{{ $label }}" @endif
        class="flex gap-1 overflow-x-auto border-b border-line [scrollbar-width:none]"
        x-on:keydown.right.prevent="getComputedStyle($el).direction === 'rtl' ? $focus.within($el).wrap().previous() : $focus.within($el).wrap().next()"
        x-on:keydown.left.prevent="getComputedStyle($el).direction === 'rtl' ? $focus.within($el).wrap().next() : $focus.within($el).wrap().previous()"
    >
        @foreach ($tabs as $key => $tab)
            @php
                $tab = is_array($tab) ? $tab : ['label' => $tab];
                $count = $tab['count'] ?? null;
            @endphp

            @if (isset($tab['href']))
                <a
                    href="{{ $tab['href'] }}"
                    wire:navigate
                    @if ($tab['active'] ?? false) aria-current="page" @endif
                    class="{{ $tabClasses }} {{ ($tab['active'] ?? false) ? $on : $off }}"
                >
                    {{ $tab['label'] }}
                    @if ($count !== null)<span class="num text-xs text-ink-subtle">{{ $count }}</span>@endif
                </a>
            @else
                <button
                    type="button"
                    role="tab"
                    id="tab-{{ $key }}"
                    x-on:click="active = @js((string) $key)"
                    x-bind:aria-selected="active === @js((string) $key) ? 'true' : 'false'"
                    x-bind:class="active === @js((string) $key) ? @js($on) : @js($off)"
                    class="{{ $tabClasses }} {{ (string) $key === (string) $active ? $on : $off }}"
                >
                    {{ $tab['label'] }}
                    @if ($count !== null)<span class="num text-xs text-ink-subtle">{{ $count }}</span>@endif
                </button>
            @endif
        @endforeach
    </div>

    @if ($slot->isNotEmpty())
        <div class="pt-4">{{ $slot }}</div>
    @endif
</div>
