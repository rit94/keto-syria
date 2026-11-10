<x-slot:context>context-slot-content</x-slot:context>

<x-slot:actions>
    <x-button variant="ghost" square icon="calendar" aria-label="actions-slot-button" />
</x-slot:actions>

<div>
    <p>page-body-content {{ $count }}</p>
    <x-button wire:click="increment">increment</x-button>

    <x-modal name="fixture-modal" wire:model="open" title="fixture modal title">
        modal-body
    </x-modal>
</div>
