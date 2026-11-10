{{--
    Application shell (signed-in pages). This is Livewire's default page layout
    (config livewire.component_layout = 'layouts::app'), so full-page components use it
    without declaring anything.

    Data (set from the component's render():  view(...)->title(...)->layoutData([...]))
      title   string|null   page title — shown in the top bar and in <title>
      wide    bool          use the wide content column (72rem instead of 44rem) for dashboards/tables
      back    string|null   URL: shows a back chevron in the top bar instead of the brand mark (sub-pages)

    Named slots (declare in the page view with <x-slot:context> … </x-slot:context>, outside the root element)
      context   small line under the title — the date context (e.g. date switcher / "Today · Monday 5 January")
      actions   page actions at the inline end of the top bar (icon buttons)

    Structure
      phones   sticky top bar · content · fixed bottom tab bar (Today, Foods, Coach, Plans, Progress)
      ≥ lg     fixed side rail (16rem) · top bar · content
    Navigation items come from App\View\Nav and only render when their route exists.
--}}
@props(['title' => null, 'wide' => false, 'back' => null])

@php
    use App\View\Nav;

    $user = auth()->user();
    $rail = Nav::rail();
    $tabs = Nav::tabs();
    $overflow = Nav::overflow();
    $smartLog = Nav::smartLogUrl();
    $hasTabs = $tabs['start'] || $tabs['coach'] || $tabs['end'];
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\View\Format::dir() }}">
<head>
    @include('layouts.partials.head', ['title' => $title])
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[70] focus:rounded-control focus:bg-surface focus:px-4 focus:py-3 focus:shadow-raised">
        {{ __('app.skip_to_content') }}
    </a>

    {{-- Side rail (desktop) --}}
    <aside class="fixed inset-y-0 start-0 z-30 hidden w-rail flex-col border-e border-line bg-surface lg:flex">
        <a href="{{ route('home') }}" class="flex h-topbar shrink-0 items-center px-5">
            <x-logo />
        </a>

        @if ($smartLog)
            <div class="px-4 pt-3">
                <x-button :href="$smartLog" icon="plus" block navigate>{{ __('nav.log') }}</x-button>
            </div>
        @endif

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="{{ __('nav.primary') }}">
            @foreach ($rail as $item)
                <a
                    href="{{ $item['url'] }}"
                    wire:navigate
                    @if ($item['active']) aria-current="page" @endif
                    @class([
                        'flex min-h-touch items-center gap-3 rounded-control px-3 text-[0.9375rem] font-medium transition-colors duration-150',
                        'bg-accent-soft text-accent-ink' => $item['active'],
                        'text-ink-muted hover:bg-surface-2 hover:text-ink' => ! $item['active'],
                    ])
                >
                    <x-icon :name="$item['icon']" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        @if ($user)
            <div class="border-t border-line p-3">
                <div class="flex items-center gap-3 px-2 py-1.5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{{ $user->name }}</p>
                        <p class="truncate text-xs text-ink-subtle" dir="ltr">{{ $user->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex size-touch items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink" aria-label="{{ __('auth.logout') }}" title="{{ __('auth.logout') }}">
                            <x-icon name="logout" />
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </aside>

    <div class="flex min-h-dvh flex-col lg:ps-rail">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 border-b border-line bg-canvas pt-safe">
            <div @class(['mx-auto flex h-topbar w-full items-center gap-2 px-gutter lg:px-8', 'max-w-page' => ! $wide, 'max-w-page-wide' => $wide])>
                @if ($back)
                    <a href="{{ $back }}" wire:navigate class="-ms-2.5 flex size-touch shrink-0 items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink" aria-label="{{ __('common.back') }}">
                        <x-icon name="chevron-start" size="lg" />
                    </a>
                @else
                    <a href="{{ route('home') }}" class="-ms-2 flex size-touch shrink-0 items-center justify-center lg:hidden" aria-label="{{ __('app.name') }}">
                        <x-logo size="sm" markOnly />
                    </a>
                @endif

                <div class="min-w-0 flex-1 ps-1">
                    <h1 class="truncate text-lg font-semibold leading-tight text-ink">{{ $title ?? __('app.name') }}</h1>
                    @isset($context)
                        <div class="truncate text-sm leading-tight text-ink-muted">{{ $context }}</div>
                    @endisset
                </div>

                <div class="flex shrink-0 items-center gap-0.5">
                    {{ $actions ?? '' }}

                    @if ($smartLog)
                        <a href="{{ $smartLog }}" wire:navigate class="flex size-touch items-center justify-center rounded-full text-accent-ink transition-colors duration-150 hover:bg-accent-soft lg:hidden" aria-label="{{ __('nav.log') }}">
                            <x-icon name="plus" size="lg" />
                        </a>
                    @endif

                    <div @class(['items-center gap-0.5', 'hidden lg:flex' => $user, 'flex' => ! $user])>
                        <x-locale-switch />
                        <x-theme-toggle />
                    </div>

                    @if ($user)
                        <button
                            type="button"
                            x-data
                            x-on:click="$dispatch('open-modal', 'account-menu')"
                            class="flex size-touch items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink lg:hidden"
                            aria-label="{{ __('app.account_menu') }}"
                            aria-haspopup="dialog"
                        >
                            <x-icon name="menu" />
                        </button>
                    @endif
                </div>
            </div>
        </header>

        {{-- Content --}}
        <main id="main" @class(['mx-auto w-full flex-1 px-gutter pt-4 lg:px-8 lg:pt-6 lg:pb-12', 'pb-tabbar' => $hasTabs, 'pb-8' => ! $hasTabs, 'max-w-page' => ! $wide, 'max-w-page-wide' => $wide])>
            {{ $slot }}
        </main>
    </div>

    {{-- Bottom tab bar (phones / tablets) --}}
    @if ($hasTabs)
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface pb-safe lg:hidden" aria-label="{{ __('nav.primary') }}">
            <div class="mx-auto grid h-tabbar max-w-lg auto-cols-fr grid-flow-col items-stretch px-1">
                @foreach ($tabs['start'] as $item)
                    @include('layouts.partials.tab', ['item' => $item])
                @endforeach

                @if ($coach = $tabs['coach'])
                    <a href="{{ $coach['url'] }}" wire:navigate @if ($coach['active']) aria-current="page" @endif class="group flex flex-col items-center justify-end gap-0.5 pb-1.5 text-[0.6875rem] font-medium leading-none {{ $coach['active'] ? 'text-accent-ink' : 'text-ink-muted' }}">
                        <span class="-mt-5 flex size-13 items-center justify-center rounded-full bg-accent text-on-accent shadow-raised ring-4 ring-surface transition-colors duration-150 group-hover:bg-accent-hover">
                            <x-icon :name="$coach['icon']" size="lg" />
                        </span>
                        <span>{{ $coach['label'] }}</span>
                    </a>
                @endif

                @foreach ($tabs['end'] as $item)
                    @include('layouts.partials.tab', ['item' => $item])
                @endforeach
            </div>
        </nav>
    @endif

    {{-- Account menu (phones) --}}
    @if ($user)
        <x-bottom-sheet name="account-menu" :title="$user->name" :description="$user->email" size="sm">
            <div class="-mx-2 space-y-0.5">
                @foreach ($overflow as $item)
                    <a href="{{ $item['url'] }}" wire:navigate class="flex min-h-touch items-center gap-3 rounded-control px-3 text-[0.9375rem] font-medium text-ink transition-colors duration-150 hover:bg-surface-2">
                        <x-icon :name="$item['icon']" class="text-ink-muted" />
                        <span class="flex-1">{{ $item['label'] }}</span>
                        <x-icon name="chevron-end" size="sm" class="text-ink-subtle" />
                    </a>
                @endforeach

                @if ($overflow)
                    <div class="mx-3 my-2 border-t border-line"></div>
                @endif

                <x-locale-switch labelled />
                <x-theme-toggle labelled />

                <div class="mx-3 my-2 border-t border-line"></div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex min-h-touch w-full items-center gap-3 rounded-control px-3 text-[0.9375rem] font-medium text-danger-ink transition-colors duration-150 hover:bg-danger-soft">
                        <x-icon name="logout" />
                        <span>{{ __('auth.logout') }}</span>
                    </button>
                </form>
            </div>
        </x-bottom-sheet>
    @endif

    <x-toaster />

    @livewireScripts
</body>
</html>
