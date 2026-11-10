{{--
    Panel for client-side <x-tabs>. Must be placed inside <x-tabs>.

    Props
      name  string  the tab key this panel belongs to (required)

    Usage  <x-tabs.panel name="weight">…</x-tabs.panel>
--}}
@props(['name'])

<div role="tabpanel" aria-labelledby="tab-{{ $name }}" x-show="active === @js((string) $name)" x-cloak {{ $attributes }}>
    {{ $slot }}
</div>
