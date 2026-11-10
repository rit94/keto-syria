{{-- Placeholder shown at "/" until the `today` route exists (see routes/web/home.php). --}}
@component('layouts::app', ['title' => __('app.home.title')])
    <x-card>
        <x-empty-state
            icon="flame"
            :title="__('app.home.placeholder_title')"
            :text="__('app.home.placeholder_text')"
        />
    </x-card>
@endcomponent
