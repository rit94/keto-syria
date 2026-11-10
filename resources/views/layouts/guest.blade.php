{{--
    Guest shell (login, register, password reset). Use from a full-page Livewire component with
    #[Layout('layouts::guest')] on the class.

    Data
      title  string|null  page title for <title>

    A centered column (max 26rem) with the brand on top and the locale + theme switches
    in the corner. The page view supplies its own heading inside the card.
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\View\Format::dir() }}">
<head>
    @include('layouts.partials.head', ['title' => $title])
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased">
    <div class="mx-auto flex min-h-dvh w-full max-w-md flex-col px-gutter pb-safe pt-safe">
        <header class="flex h-topbar items-center justify-between">
            <a href="{{ url('/') }}" aria-label="{{ __('app.name') }}">
                <x-logo size="sm" />
            </a>
            <div class="-me-2 flex items-center gap-0.5">
                <x-locale-switch />
                <x-theme-toggle />
            </div>
        </header>

        <main id="main" class="flex flex-1 flex-col justify-center py-8">
            {{ $slot }}
        </main>

        <footer class="py-5 text-center text-sm text-ink-subtle">
            {{ __('app.tagline') }}
        </footer>
    </div>

    <x-toaster />

    @livewireScripts
</body>
</html>
