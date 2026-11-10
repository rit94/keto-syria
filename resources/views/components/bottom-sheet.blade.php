{{--
    Bottom sheet — <x-modal sheet>. Rises from the bottom edge on phones (thumb reach,
    safe-area aware) and becomes a centered dialog from sm up. Same props, slots and
    open/close API as x-modal (see modal.blade.php).

    Props
      name, title, description, size (sm|md|lg), dismissible, show

    Usage
      <x-button icon="plus" x-on:click="$dispatch('open-modal', 'add-food')">{{ __('common.add') }}</x-button>

      <x-bottom-sheet name="add-food" :title="__('log.add_food')">
          <x-number-stepper wire:model="quantity" :label="__('log.quantity')" />
          <x-slot:footer>
              <x-button wire:click="add" block>{{ __('common.add') }}</x-button>
          </x-slot:footer>
      </x-bottom-sheet>
--}}
@props(['name', 'title' => null, 'description' => null, 'size' => 'md', 'dismissible' => true, 'show' => false])

<x-modal sheet :name="$name" :title="$title" :description="$description" :size="$size" :dismissible="$dismissible" :show="$show" {{ $attributes }}>
    {{ $slot }}

    @isset($footer)
        <x-slot:footer>{{ $footer }}</x-slot:footer>
    @endisset
</x-modal>
