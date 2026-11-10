{{--
    Living style guide (/_ui, local + testing only). Renders the showcase partial twice:
    once in Arabic / RTL and once in English / LTR, regardless of the current UI locale.
--}}
@php
    $currentLocale = app()->getLocale();
@endphp

@component('layouts::app', ['title' => __('ui.title'), 'wide' => true])
    <p class="mb-6 max-w-2xl text-ink-muted">{{ __('ui.intro') }}</p>

    <div class="grid gap-x-8 gap-y-10 xl:grid-cols-2">
        @foreach (['ar' => 'rtl', 'en' => 'ltr'] as $panelLocale => $panelDir)
            @php app()->setLocale($panelLocale); @endphp

            <section dir="{{ $panelDir }}" lang="{{ $panelLocale }}" data-ui-panel="{{ $panelLocale }}" class="min-w-0">
                <p class="mb-4 inline-flex rounded-full bg-accent-soft px-3 py-1 text-sm font-semibold text-accent-ink">{{ __('ui.panel') }}</p>

                @include('ui.partials.showcase', ['p' => $panelLocale])
            </section>
        @endforeach

        @php app()->setLocale($currentLocale); @endphp
    </div>
@endcomponent
