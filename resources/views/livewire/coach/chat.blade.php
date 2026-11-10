{{--
    AI coach chat. Class: App\Livewire\Coach\Chat

    Left column (≥ lg) / bottom sheet (phones): conversations. Right: thread + sticky composer.
    Assistant text is rendered through App\Livewire\Coach\Support\Markdown (escaped first).
--}}
<x-slot:context>{{ $conversation?->title ?: __('coach_ui.new_chat') }}</x-slot:context>

<x-slot:actions>
    <button
        type="button"
        x-data
        x-on:click="$dispatch('open-modal', 'coach-conversations')"
        class="flex size-touch items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink lg:hidden"
        aria-label="{{ __('coach_ui.conversations') }}"
        aria-haspopup="dialog"
    >
        <x-icon name="list" />
    </button>
    <a
        href="{{ route('coach') }}"
        wire:navigate
        class="flex size-touch items-center justify-center rounded-full text-accent-ink transition-colors duration-150 hover:bg-accent-soft"
        aria-label="{{ __('coach_ui.new_chat') }}"
        title="{{ __('coach_ui.new_chat') }}"
    >
        <x-icon name="edit" />
    </a>
</x-slot:actions>

<div
    class="lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-8"
    x-data="{
        pending: '',
        deleteId: null,
        scrollDown(instant = false) {
            this.$nextTick(() => {
                const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                window.scrollTo({ top: document.documentElement.scrollHeight, behavior: reduce || instant ? 'auto' : 'smooth' });
            });
        },
    }"
    x-init="@if ($conversation) scrollDown(true) @endif"
    x-on:coach-scroll.window="scrollDown()"
>
    {{-- Conversations (desktop) --}}
    <aside class="hidden lg:block" aria-label="{{ __('coach_ui.conversations') }}">
        <div class="sticky top-[calc(var(--spacing-topbar)+1.5rem)] space-y-3">
            <x-button :href="route('coach')" navigate icon="plus" variant="secondary" block>{{ __('coach_ui.new_chat') }}</x-button>
            @include('livewire.coach.partials.conversation-list', ['conversations' => $conversations, 'current' => $conversation])
        </div>
    </aside>

    <section class="min-w-0 lg:mx-auto lg:w-full lg:max-w-3xl" aria-label="{{ __('coach_ui.title') }}">
        {{-- Thread --}}
        <div
            id="coach-thread"
            role="log"
            aria-live="polite"
            aria-relevant="additions"
            aria-label="{{ __('coach_ui.thread_label') }}"
            class="space-y-4 pb-4"
        >
            @forelse ($thread as $entry)
                @php($isUser = $entry['role'] === 'user')

                <div wire:key="msg-{{ $entry['id'] }}" class="space-y-3">
                    <div @class(['flex', 'justify-end' => $isUser])>
                        <div
                            @class([
                                'max-w-[88%] rounded-2xl px-4 py-2.5 text-[0.9375rem] leading-relaxed sm:max-w-[80%]',
                                'rounded-ee-md bg-accent text-on-accent' => $isUser,
                                'rounded-es-md border border-line bg-surface text-ink shadow-card' => ! $isUser,
                            ])
                        >
                            <p class="sr-only">{{ $isUser ? __('coach_ui.you') : __('coach_ui.coach') }}</p>
                            @if ($entry['has_image'])
                                <p class="mb-1 flex items-center gap-1.5 text-sm opacity-90">
                                    <x-icon name="camera" size="sm" />
                                    <span>{{ __('coach_ui.photo_sent') }}</span>
                                </p>
                            @endif
                            @if ($isUser)
                                <p class="whitespace-pre-line break-words">{{ $entry['text'] }}</p>
                            @else
                                <div class="break-words [&_li]:mt-1 [&_ol]:list-decimal [&_ol]:ps-5 [&_p+p]:mt-2 [&_p+ul]:mt-2 [&_p+ol]:mt-2 [&_ul+p]:mt-2 [&_ol+p]:mt-2 [&_ul]:list-disc [&_ul]:ps-5">{!! \App\Livewire\Coach\Support\Markdown::render($entry['text']) !!}</div>
                            @endif
                        </div>
                    </div>

                    @if (! $isUser)
                        @foreach ($uiActions[$entry['id']] ?? [] as $action)
                            @if ($action['type'] === 'open_custom_food_form' && \Illuminate\Support\Facades\Route::has('foods.create'))
                                <x-card padding="sm" class="me-auto max-w-[92%] sm:max-w-[85%]" wire:key="action-{{ $entry['id'] }}-{{ $loop->index }}">
                                    <div class="flex items-start gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-info-soft text-info-ink"><x-icon name="plus" size="sm" /></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-ink">{{ __('coach_ui.custom_food.title', ['name' => $action['name']]) }}</p>
                                            <p class="mt-0.5 text-sm text-ink-muted">{{ __('coach_ui.custom_food.text') }}</p>
                                            <x-button class="mt-3" size="sm" variant="secondary" icon="plus" :href="route('foods.create', ['name' => $action['name']])" navigate>
                                                {{ __('coach_ui.custom_food.cta') }}
                                            </x-button>
                                        </div>
                                    </div>
                                </x-card>
                            @endif
                        @endforeach

                        @foreach ($entry['proposals'] as $proposal)
                            @include('livewire.coach.partials.proposal', ['proposal' => $proposal])
                        @endforeach
                    @endif
                </div>
            @empty
                <div class="py-6 text-center">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-accent-soft text-accent-ink">
                        <x-icon name="sparkles" size="lg" />
                    </span>
                    <h2 class="mt-4 text-xl font-semibold text-ink">{{ __('coach_ui.empty.title') }}</h2>
                    <p class="mx-auto mt-1 max-w-sm text-[0.9375rem] text-ink-muted">{{ __('coach_ui.empty.text') }}</p>

                    @if ($configured)
                        <ul class="mx-auto mt-6 grid max-w-md gap-2 text-start">
                            @foreach (\App\Livewire\Coach\Chat::STARTERS as $starter)
                                <li>
                                    <button
                                        type="button"
                                        wire:click="useStarter('{{ $starter }}')"
                                        x-on:click="pending = {{ \Illuminate\Support\Js::from(__('coach_ui.starters.'.$starter.'.prompt')) }}; scrollDown()"
                                        wire:loading.attr="disabled"
                                        wire:target="send,useStarter"
                                        class="flex min-h-touch w-full items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 text-start text-[0.9375rem] text-ink shadow-card transition-colors duration-150 hover:border-line-strong hover:bg-surface-2 disabled:opacity-60"
                                    >
                                        <x-icon name="{{ ['tonight' => 'flame', 'eggplant' => 'book', 'plan' => 'calendar', 'carbs' => 'chart'][$starter] }}" class="shrink-0 text-accent-ink" />
                                        <span>{{ __('coach_ui.starters.'.$starter.'.label') }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforelse

            {{-- The turn that is running right now (the real messages replace this when the request ends) --}}
            <div wire:loading.flex wire:target="send,useStarter" class="hidden flex-col gap-3" aria-hidden="true">
                <div class="flex justify-end" x-show="pending" x-cloak>
                    <div class="max-w-[88%] whitespace-pre-line break-words rounded-2xl rounded-ee-md bg-accent px-4 py-2.5 text-[0.9375rem] leading-relaxed text-on-accent opacity-80 sm:max-w-[80%]" x-text="pending"></div>
                </div>
                <div class="flex">
                    <div class="flex items-center gap-1.5 rounded-2xl rounded-es-md border border-line bg-surface px-4 py-3.5 shadow-card">
                        <span class="size-2 animate-pulse rounded-full bg-ink-subtle"></span>
                        <span class="size-2 animate-pulse rounded-full bg-ink-subtle [animation-delay:150ms]"></span>
                        <span class="size-2 animate-pulse rounded-full bg-ink-subtle [animation-delay:300ms]"></span>
                    </div>
                </div>
            </div>
            <p class="sr-only" wire:loading wire:target="send,useStarter" role="status">{{ __('coach_ui.thinking') }}</p>
        </div>

        {{-- Composer --}}
        <div class="sticky bottom-tabbar z-10 -mx-gutter border-t border-line bg-canvas px-gutter pb-3 pt-3 lg:bottom-0 lg:mx-0 lg:border-t-0 lg:px-0">
            @if (! $configured)
                <x-alert variant="warning" :title="__('coach_ui.not_configured.title')">
                    <p>{{ __('coach_ui.not_configured.text') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if (\Illuminate\Support\Facades\Route::has('today'))
                            <x-button size="sm" variant="secondary" icon="flame" :href="route('today')" navigate>{{ __('coach_ui.not_configured.manual') }}</x-button>
                        @endif
                        @if (\Illuminate\Support\Facades\Route::has('foods.index'))
                            <x-button size="sm" variant="secondary" icon="search" :href="route('foods.index')" navigate>{{ __('coach_ui.not_configured.foods') }}</x-button>
                        @endif
                    </div>
                </x-alert>
            @else
                @if ($notice)
                    <x-alert :variant="$notice['type'] === 'rate_limited' ? 'warning' : 'danger'" class="mb-3">
                        <p>{{ $notice['message'] }}</p>
                        @if (in_array($notice['type'], ['unavailable', 'error'], true))
                            <x-button class="mt-2" size="sm" variant="secondary" icon="send" wire:click="send" x-on:click="pending = $wire.message">{{ __('common.retry') }}</x-button>
                        @endif
                    </x-alert>
                @endif

                @error('message') <p class="mb-2 text-sm text-danger-ink" role="alert">{{ $message }}</p> @enderror
                @error('photo') <p class="mb-2 text-sm text-danger-ink" role="alert">{{ $message }}</p> @enderror

                @if ($photo)
                    <div class="mb-2 flex">
                        <span class="inline-flex items-center gap-2 rounded-full border border-line bg-surface py-1 ps-1 pe-2 text-sm text-ink-muted shadow-card">
                            @if ($photoUrl)
                                <img src="{{ $photoUrl }}" alt="" class="size-9 rounded-full object-cover">
                            @else
                                <span class="flex size-9 items-center justify-center rounded-full bg-surface-2"><x-icon name="camera" size="sm" /></span>
                            @endif
                            <span>{{ __('coach_ui.composer.photo_attached') }}</span>
                            <button type="button" wire:click="removePhoto" class="-me-1 flex size-touch items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink" aria-label="{{ __('coach_ui.composer.remove_photo') }}">
                                <x-icon name="x" size="sm" />
                            </button>
                        </span>
                    </div>
                @endif

                <form
                    wire:submit="send"
                    x-on:submit="pending = $refs.message.value"
                    class="flex items-end gap-1 rounded-[1.5rem] border border-line-strong bg-surface p-1.5 shadow-card focus-within:outline-2 focus-within:outline-offset-1 focus-within:outline-focus"
                >
                    <label class="relative flex size-touch shrink-0 cursor-pointer items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink has-focus-visible:outline-2 has-focus-visible:outline-focus" title="{{ __('coach_ui.composer.attach') }}">
                        <x-icon name="camera" />
                        <span class="sr-only">{{ __('coach_ui.composer.attach') }}</span>
                        <input
                            type="file"
                            wire:key="coach-photo-input-{{ $photoKey }}"
                            wire:model="photo"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            class="sr-only"
                            wire:loading.attr="disabled"
                            wire:target="send,useStarter"
                        >
                    </label>

                    <textarea
                        id="coach-message"
                        x-ref="message"
                        wire:model="message"
                        rows="1"
                        maxlength="{{ (int) config('ai.coach.max_input_chars', 4000) }}"
                        placeholder="{{ __('coach_ui.composer.placeholder') }}"
                        aria-label="{{ __('coach_ui.composer.label') }}"
                        wire:loading.attr="disabled"
                        wire:target="send,useStarter"
                        class="max-h-40 min-h-touch min-w-0 flex-1 resize-none self-center border-0 bg-transparent px-2 py-2.5 text-base leading-snug text-ink placeholder:text-ink-subtle focus:outline-none disabled:opacity-60"
                        x-data="{
                            fit() { this.$el.style.height = 'auto'; this.$el.style.height = Math.min(this.$el.scrollHeight, 160) + 'px'; },
                        }"
                        x-init="fit(); $watch('$wire.message', () => $nextTick(() => fit()))"
                        x-on:input="fit()"
                        x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing && window.matchMedia('(pointer: fine)').matches) { $event.preventDefault(); $el.form.requestSubmit(); }"
                    ></textarea>

                    <div
                        x-data="voiceInput({{ \Illuminate\Support\Js::from(['locale' => app()->getLocale(), 'target' => '#coach-message', 'denied' => __('coach_ui.voice.denied'), 'failed' => __('coach_ui.voice.failed')]) }})"
                        x-show="supported"
                        x-cloak
                        class="shrink-0"
                    >
                        <button
                            type="button"
                            x-on:click="toggle()"
                            :aria-pressed="listening.toString()"
                            :class="listening ? 'bg-danger-soft text-danger-ink' : 'text-ink-muted hover:bg-surface-2 hover:text-ink'"
                            class="flex size-touch items-center justify-center rounded-full transition-colors duration-150"
                            aria-label="{{ __('coach_ui.voice.toggle') }}"
                            title="{{ __('coach_ui.voice.toggle') }}"
                        >
                            <x-icon name="mic" />
                        </button>
                        <p class="sr-only" role="status" x-text="listening ? @js(__('coach_ui.voice.listening')) : error"></p>
                    </div>

                    <button
                        type="submit"
                        class="flex size-touch shrink-0 items-center justify-center rounded-full bg-accent text-on-accent transition-colors duration-150 hover:bg-accent-hover disabled:opacity-50"
                        wire:loading.attr="disabled"
                        wire:target="send,useStarter"
                        aria-label="{{ __('coach_ui.composer.send') }}"
                        title="{{ __('coach_ui.composer.send') }}"
                    >
                        <span wire:loading.remove wire:target="send,useStarter"><x-icon name="send" /></span>
                        <span wire:loading wire:target="send,useStarter"><x-icon name="spinner" /></span>
                    </button>
                </form>

                <p class="mt-2 px-2 text-center text-xs text-ink-subtle">{{ __('coach_ui.composer.disclaimer') }}</p>
            @endif
        </div>
    </section>

    {{-- Conversations (phones) --}}
    <x-bottom-sheet name="coach-conversations" :title="__('coach_ui.conversations')" size="sm">
        <x-button :href="route('coach')" navigate icon="plus" variant="secondary" block class="mb-3">{{ __('coach_ui.new_chat') }}</x-button>
        @include('livewire.coach.partials.conversation-list', ['conversations' => $conversations, 'current' => $conversation])
    </x-bottom-sheet>

    <x-modal name="delete-conversation" :title="__('coach_ui.delete.title')" size="sm">
        <p class="text-ink-muted">{{ __('coach_ui.delete.text') }}</p>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'delete-conversation')">{{ __('common.cancel') }}</x-button>
            <x-button variant="danger" icon="trash" x-on:click="$wire.deleteConversation(deleteId)">{{ __('common.delete') }}</x-button>
        </x-slot:footer>
    </x-modal>
</div>
