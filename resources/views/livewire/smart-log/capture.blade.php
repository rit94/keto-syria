{{--
    Smart food logging. Class: App\Livewire\SmartLog\Capture

    input (text / voice / photo)  →  editable draft  →  "Confirm and log"
    Nutrition numbers shown here are the draft's own (engine) numbers; the model never supplies any.
--}}
<x-slot:actions>
    @if (\Illuminate\Support\Facades\Route::has('coach'))
        <a
            href="{{ route('coach') }}"
            wire:navigate
            class="flex size-touch items-center justify-center rounded-full text-accent-ink transition-colors duration-150 hover:bg-accent-soft"
            aria-label="{{ __('smart_log.ask_coach') }}"
            title="{{ __('smart_log.ask_coach') }}"
        >
            <x-icon name="chat" />
        </a>
    @endif
</x-slot:actions>

<div class="space-y-4">
    @if (! $configured)
        <x-alert variant="warning" :title="__('smart_log.not_configured.title')">
            <p>{{ __('smart_log.not_configured.text') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @if (\Illuminate\Support\Facades\Route::has('today'))
                    <x-button size="sm" variant="secondary" icon="flame" :href="route('today')" navigate>{{ __('smart_log.not_configured.manual') }}</x-button>
                @endif
                @if (\Illuminate\Support\Facades\Route::has('foods.index'))
                    <x-button size="sm" variant="secondary" icon="search" :href="route('foods.index')" navigate>{{ __('smart_log.not_configured.foods') }}</x-button>
                @endif
            </div>
        </x-alert>
    @endif

    @if ($notice)
        <x-alert :variant="$notice['type'] === 'rate_limited' ? 'warning' : 'danger'">
            <p>{{ $notice['message'] }}</p>
            @if (in_array($notice['type'], ['unavailable', 'error'], true) && ! $draftModel)
                <x-button class="mt-2" size="sm" variant="secondary" icon="send" wire:click="analyze">{{ __('common.retry') }}</x-button>
            @endif
        </x-alert>
    @endif

    {{-- ───────────── Done ───────────── --}}
    @if ($logged)
        @php
            $loggedMeal = \App\Enums\Meal::tryFrom($logged['meal']);
        @endphp
        <x-card padding="lg" class="text-center" role="status">
            <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-success-soft text-success-ink"><x-icon name="check" size="lg" /></span>
            <h2 class="mt-4 text-xl font-semibold text-ink">{{ __('smart_log.done.title') }}</h2>
            <p class="mt-1 text-[0.9375rem] text-ink-muted">
                {{ __('smart_log.done.text', ['count' => $logged['count'], 'meal' => $loggedMeal?->label() ?? '', 'date' => \App\View\Format::date($logged['date'], 'weekday')]) }}
            </p>
            <div class="mt-5 flex flex-wrap justify-center gap-2">
                @if (\Illuminate\Support\Facades\Route::has('today'))
                    <x-button :href="route('today')" navigate iconEnd="chevron-end">{{ __('smart_log.done.open_today') }}</x-button>
                @endif
                <x-button variant="secondary" icon="plus" wire:click="startOver">{{ __('smart_log.done.log_another') }}</x-button>
            </div>
        </x-card>

    {{-- ───────────── Input ───────────── --}}
    @elseif (! $draftModel)
        <x-card padding="lg" class="space-y-4">
            <x-segmented wire:model.live="mode" :label="__('smart_log.mode.label')" :options="['text' => __('smart_log.mode.text'), 'voice' => __('smart_log.mode.voice'), 'photo' => __('smart_log.mode.photo')]" :selected="$mode" />

            <form wire:submit="analyze" class="space-y-4" novalidate>
                @if ($mode === 'photo')
                    <div class="space-y-3">
                        <p class="text-[0.9375rem] text-ink-muted">{{ __('smart_log.photo.intro') }}</p>

                        @if ($photo)
                            <div class="flex items-center gap-3 rounded-control border border-line p-2">
                                @if ($photoUrl)
                                    <img src="{{ $photoUrl }}" alt="{{ __('smart_log.photo.preview_alt') }}" class="size-20 rounded-control object-cover">
                                @else
                                    <span class="flex size-20 items-center justify-center rounded-control bg-surface-2 text-ink-muted"><x-icon name="camera" size="lg" /></span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="text-[0.9375rem] font-medium text-ink">{{ __('smart_log.photo.ready') }}</p>
                                    <x-button type="button" size="sm" variant="ghost" icon="x" wire:click="removePhoto">{{ __('smart_log.photo.remove') }}</x-button>
                                </div>
                            </div>
                        @else
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ([['camera', 'take', true], ['plus', 'choose', false]] as [$icon, $labelKey, $capture])
                                    <label class="relative flex min-h-13 cursor-pointer items-center justify-center gap-2 rounded-control border border-line-strong bg-surface px-4 text-[0.9375rem] font-semibold text-ink transition-colors duration-150 hover:bg-surface-2 has-focus-visible:outline-2 has-focus-visible:outline-offset-1 has-focus-visible:outline-focus">
                                        <x-icon :name="$icon" />
                                        <span>{{ __('smart_log.photo.'.$labelKey) }}</span>
                                        <input
                                            type="file"
                                            wire:key="smart-photo-{{ $labelKey }}-{{ $photoKey }}"
                                            wire:model="photo"
                                            accept="image/jpeg,image/png,image/webp,image/gif"
                                            @if ($capture) capture="environment" @endif
                                            class="sr-only"
                                            @disabled(! $configured)
                                        >
                                    </label>
                                @endforeach
                            </div>
                            <div wire:loading wire:target="photo" class="text-sm text-ink-muted" role="status">{{ __('smart_log.photo.uploading') }}</div>
                        @endif

                        @error('photo') <p class="text-sm text-danger-ink" role="alert">{{ $message }}</p> @enderror

                        <x-textarea wire:model="text" id="smart-log-text" rows="2" :label="__('smart_log.photo.note')" :placeholder="__('smart_log.photo.note_placeholder')" optional :error="$errors->first('text')" />
                    </div>
                @else
                    <div class="space-y-3" wire:key="smart-input-{{ $mode }}">
                        @if ($mode === 'voice')
                            <div
                                x-data="voiceInput({{ \Illuminate\Support\Js::from(['locale' => app()->getLocale(), 'target' => '#smart-log-text', 'denied' => __('coach_ui.voice.denied'), 'failed' => __('coach_ui.voice.failed')]) }})"
                                class="flex flex-col items-center gap-3 py-2 text-center"
                            >
                                <template x-if="supported">
                                    <div class="flex flex-col items-center gap-3">
                                        <button
                                            type="button"
                                            x-on:click="toggle()"
                                            :aria-pressed="listening.toString()"
                                            :class="listening ? 'bg-danger text-on-danger' : 'bg-accent text-on-accent hover:bg-accent-hover'"
                                            class="flex size-20 items-center justify-center rounded-full shadow-raised transition-colors duration-150"
                                            aria-label="{{ __('coach_ui.voice.toggle') }}"
                                        >
                                            <x-icon name="mic" size="xl" />
                                        </button>
                                        <p class="min-h-6 text-[0.9375rem] text-ink-muted" role="status" x-text="listening ? @js(__('smart_log.voice.listening')) : (error || @js(__('smart_log.voice.tap')))"></p>
                                    </div>
                                </template>
                                <template x-if="! supported">
                                    <x-alert variant="info" class="text-start">{{ __('smart_log.voice.unsupported') }}</x-alert>
                                </template>
                            </div>
                        @endif

                        <x-textarea
                            wire:model="text"
                            id="smart-log-text"
                            rows="4"
                            :label="$mode === 'voice' ? __('smart_log.voice.transcript') : __('smart_log.text.label')"
                            :placeholder="$mode === 'voice' ? __('smart_log.voice.transcript_placeholder') : __('smart_log.text.placeholder')"
                            :hint="$mode === 'voice' ? __('smart_log.voice.hint') : __('smart_log.text.hint')"
                            :error="$errors->first('text')"
                            maxlength="{{ (int) config('ai.parser.max_input_chars', 2000) }}"
                        />
                    </div>
                @endif

                <x-button type="submit" loading="analyze" size="lg" block icon="sparkles" :disabled="! $configured">
                    {{ $mode === 'photo' ? __('smart_log.analyze_photo') : __('smart_log.analyze') }}
                </x-button>
                <p wire:loading wire:target="analyze" class="text-center text-sm text-ink-muted" role="status">{{ __('smart_log.analyzing') }}</p>
            </form>
        </x-card>

        <p class="px-1 text-center text-sm text-ink-muted">
            {{ __('smart_log.footer') }}
            @if (\Illuminate\Support\Facades\Route::has('coach'))
                <a href="{{ route('coach') }}" wire:navigate class="font-medium text-accent-ink underline underline-offset-2">{{ __('smart_log.ask_coach') }}</a>
            @endif
        </p>

    {{-- ───────────── Review the draft ───────────── --}}
    @else
        @php
            $ambiguousKeys = array_column(array_filter($draftModel->items, fn ($item) => $item['status'] === \App\Services\Ai\SmartLog\FoodDraft::AMBIGUOUS), 'key');
            $canCommit = $draftModel->isCommittable() && $ambiguousKeys === [] && (! $draftModel->requiresConfirmation || $acknowledged);
            $statusStyle = [
                'resolved' => ['success', 'check'],
                'ambiguous' => ['warning', 'alert'],
                'needs_quantity' => ['warning', 'edit'],
                'unresolved' => ['danger', 'search'],
            ];
        @endphp

        @if ($draftModel->origin === 'image')
            <x-alert variant="warning" icon="camera">{{ __('coach.draft.photo_notice') }}</x-alert>
        @endif

        {{-- Clarification questions --}}
        @if ($draftModel->questions)
            <x-card padding="sm" class="space-y-3 border-info/40" role="group" aria-label="{{ __('smart_log.questions.title') }}">
                <p class="flex items-center gap-2 font-semibold text-info-ink"><x-icon name="info" size="sm" /> {{ __('smart_log.questions.title') }}</p>
                @foreach ($draftModel->questions as $index => $question)
                    <form wire:submit="answerQuestion({{ $index }})" class="space-y-2" wire:key="question-{{ $index }}">
                        <label for="answer-{{ $index }}" class="block text-[0.9375rem] font-medium text-ink">{{ $question }}</label>
                        <div class="flex gap-2">
                            <input id="answer-{{ $index }}" type="text" wire:model="answers.{{ $index }}" class="control min-w-0 flex-1" placeholder="{{ __('smart_log.questions.placeholder') }}">
                            <x-button type="submit" loading="answerQuestion({{ $index }})" variant="secondary">{{ __('smart_log.questions.answer') }}</x-button>
                        </div>
                    </form>
                @endforeach
                <p class="text-sm text-ink-muted">{{ __('smart_log.questions.hint') }}</p>
            </x-card>
        @endif

        {{-- Meal + date --}}
        <x-card padding="sm" class="space-y-3">
            <x-segmented wire:model.live="meal" :label="__('smart_log.meal')" showLabel :options="$mealOptions" :selected="$meal" />
            @if ($draftModel->mealInferred)
                <p class="text-sm text-ink-muted">{{ __('smart_log.meal_guessed') }}</p>
            @endif
            <x-input type="date" wire:model.live="date" :label="__('smart_log.date')" max="{{ \App\Services\Ai\Support\AiClock::today()->toDateString() }}" />
        </x-card>

        {{-- Items --}}
        <div class="space-y-3" aria-live="polite">
            <h2 class="px-1 text-base font-semibold text-ink">{{ __('smart_log.items.title') }}</h2>

            @forelse ($draftModel->items as $item)
                @php
                    $key = $item['key'];
                    [$tone, $statusIcon] = $statusStyle[$item['status']];
                    $isBlocked = in_array($key, $blocked, true);
                    $others = array_values(array_filter($item['candidates'], fn ($c) => $c['food_id'] !== $item['food_id']));
                    $issues = array_values(array_filter($item['issues'], fn ($i) => $i !== 'estimated_values'));
                @endphp

                <x-card
                    padding="sm"
                    wire:key="item-{{ $key }}"
                    :class="$isBlocked ? 'space-y-3 ring-2 ring-danger' : 'space-y-3'"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs text-ink-muted">{{ __('smart_log.items.you_said') }} <bdi>«{{ $item['spoken_name'] }}»</bdi></p>
                            <p class="text-[0.9375rem] font-semibold leading-snug text-ink">
                                {{ $item['food_name'] ?? __('smart_log.items.no_food') }}
                            </p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <x-badge :variant="$tone" :icon="$statusIcon">{{ __('coach.draft.status.'.$item['status']) }}</x-badge>
                                @if ($item['is_estimate'])<x-badge estimate />@endif
                                @if ($item['is_cooking_fat'])<x-badge>{{ __('smart_log.items.cooking_fat') }}</x-badge>@endif
                            </div>
                        </div>
                        <button
                            type="button"
                            wire:click="removeItem('{{ $key }}')"
                            class="-me-2 -mt-1 flex size-touch shrink-0 items-center justify-center rounded-full text-ink-muted transition-colors duration-150 hover:bg-danger-soft hover:text-danger-ink"
                            aria-label="{{ __('smart_log.items.remove', ['name' => $item['food_name'] ?? $item['spoken_name']]) }}"
                        >
                            <x-icon name="trash" size="sm" />
                        </button>
                    </div>

                    @foreach ($issues as $issue)
                        <p class="flex items-start gap-2 text-sm {{ in_array($issue, ['no_match', 'food_not_accessible', 'missing_quantity', 'missing_unit', 'unknown_unit', 'not_calculable'], true) ? 'text-danger-ink' : 'text-ink-muted' }}">
                            <x-icon name="{{ in_array($issue, ['no_match', 'food_not_accessible', 'missing_quantity', 'missing_unit', 'unknown_unit', 'not_calculable'], true) ? 'alert' : 'info' }}" size="sm" class="mt-0.5 shrink-0" />
                            <span>{{ __('coach.draft.issues.'.$issue) }}</span>
                        </p>
                    @endforeach

                    {{-- Which food --}}
                    @if ($item['status'] === 'ambiguous')
                        <x-button size="sm" icon="check" wire:click="confirmFood('{{ $key }}')">{{ __('smart_log.items.confirm_food') }}</x-button>
                    @endif

                    @if ($others)
                        <div>
                            <p class="mb-1.5 text-sm font-medium text-ink">{{ $item['food_id'] ? __('smart_log.items.did_you_mean') : __('smart_log.items.pick_one') }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($others as $candidate)
                                    <button
                                        type="button"
                                        wire:click="chooseCandidate('{{ $key }}', {{ $candidate['food_id'] }})"
                                        class="min-h-touch rounded-full border border-line-strong bg-surface px-3.5 text-sm font-medium text-ink transition-colors duration-150 hover:bg-surface-2"
                                    >{{ $candidate['name'] }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (class_exists(\App\Livewire\Foods\Picker::class))
                        <x-button size="sm" variant="ghost" icon="search" wire:click="openPicker('{{ $key }}')">
                            {{ $item['food_id'] ? __('smart_log.items.change_food') : __('smart_log.items.search_food') }}
                        </x-button>
                    @endif

                    {{-- How much --}}
                    @if ($item['food_id'])
                        <div class="grid grid-cols-2 gap-3">
                            <x-number-stepper
                                wire:model.live.debounce.500ms="rows.{{ $key }}.quantity"
                                id="rows-{{ $key }}-quantity"
                                :label="__('smart_log.items.quantity')"
                                :step="($item['unit'] ?? '') === 'g' ? 10 : 0.5"
                            />
                            <x-select
                                wire:model.live="rows.{{ $key }}.unit"
                                id="rows-{{ $key }}-unit"
                                :label="__('smart_log.items.unit')"
                                :options="collect($item['units'])->mapWithKeys(fn ($u) => [$u['unit'] => $u['label']])->all()"
                                :selected="$item['unit']"
                                :placeholder="__('smart_log.items.choose_unit')"
                            />
                        </div>

                        @if ($canCooked($item))
                            <div>
                                <x-checkbox wire:model.live="rows.{{ $key }}.weighed_cooked" id="rows-{{ $key }}-cooked" :label="__('smart_log.items.weighed_cooked')" />
                                <p class="ps-8 text-sm text-ink-muted">{{ __('smart_log.items.weighed_cooked_hint') }}</p>
                            </div>
                        @endif
                    @endif

                    @if ($item['nutrition'])
                        <p class="border-t border-line pt-2 text-sm text-ink-muted">
                            <span class="num font-semibold text-ink">{{ \App\View\Format::number($item['nutrition']['calories'], 0) }}</span> {{ __('common.units.kcal') }}
                            · <span class="num font-semibold text-ink">{{ \App\View\Format::number($item['nutrition']['net_carbs_g'], 1) }}</span> {{ __('common.units.g') }} {{ __('common.macros.net_carbs') }}
                            · <span class="num">{{ \App\View\Format::number($item['nutrition']['protein_g'], 1) }}</span> {{ __('common.units.g') }} {{ __('common.macros.protein') }}
                            · <span class="num">{{ \App\View\Format::number($item['nutrition']['fat_g'], 1) }}</span> {{ __('common.units.g') }} {{ __('common.macros.fat') }}
                        </p>
                    @endif
                </x-card>
            @empty
                <x-card padding="sm"><p class="text-[0.9375rem] text-ink-muted">{{ __('coach.draft.no_food_found') }}</p></x-card>
            @endforelse

            @if (class_exists(\App\Livewire\Foods\Picker::class))
                <x-button variant="secondary" icon="plus" block wire:click="openPicker">{{ __('smart_log.items.add') }}</x-button>
            @endif
        </div>

        {{-- Totals --}}
        @if ($draftModel->totals)
            <x-card padding="sm" class="space-y-3">
                @include('livewire.coach.partials.nutrition', ['n' => $draftModel->totals, 'caption' => __('smart_log.totals.title')])
                @if ($draftModel->totalsPartial)
                    <p class="flex items-start gap-1.5 text-sm text-warning-ink"><x-icon name="alert" size="sm" class="mt-0.5 shrink-0" /> {{ __('smart_log.totals.partial') }}</p>
                @endif
                @if ($day['remaining'])
                    @include('livewire.coach.partials.remaining', ['r' => $day['remaining']])
                @elseif (! $day['has_targets'])
                    <p class="text-sm text-ink-muted">{{ __('smart_log.totals.no_targets') }}</p>
                @endif
            </x-card>
        @endif

        {{-- Confirm --}}
        <div class="space-y-3 pb-2">
            @if ($draftModel->requiresConfirmation && $ambiguousKeys === [])
                <div>
                    <x-checkbox wire:model.live="acknowledged" id="smart-log-acknowledged" :label="__('smart_log.review.acknowledge')" />
                    @error('acknowledged') <p class="ps-8 text-sm text-danger-ink" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($ambiguousKeys)
                <p class="text-sm text-warning-ink">{{ __('smart_log.errors.confirm_ambiguous') }}</p>
            @elseif (! $draftModel->isCommittable())
                <p class="text-sm text-ink-muted">{{ $draftModel->items === [] ? __('coach.draft.errors.empty') : __('coach.draft.errors.unresolved_items') }}</p>
            @endif

            <div class="flex flex-col gap-2 sm:flex-row-reverse">
                <x-button wire:click="commit" size="lg" icon="check" class="sm:flex-1" :disabled="! $canCommit">{{ __('smart_log.confirm') }}</x-button>
                <x-button wire:click="startOver" variant="ghost" size="lg">{{ __('smart_log.start_over') }}</x-button>
            </div>
        </div>
    @endif

    {{-- Shared food picker (change a row's food / add a row) --}}
    @if (class_exists(\App\Livewire\Foods\Picker::class))
        <x-bottom-sheet name="smart-log-picker" :title="__('smart_log.picker.title')" size="lg">
            <livewire:foods.picker :context="\App\Livewire\SmartLog\Capture::PICKER_CONTEXT" :with-quantity="true" :key="'smart-log-picker'" />
        </x-bottom-sheet>
    @endif
</div>
