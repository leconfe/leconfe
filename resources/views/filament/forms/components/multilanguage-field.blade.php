@php
    $childComponentContainers = $getChildComponentContainers();
    $primaryContainer = array_shift($childComponentContainers);
    $translationStatePaths = $getTranslationStatePaths();
    $total = count($translationStatePaths);
    $hasTranslationErrors = collect($translationStatePaths)->contains(fn (string $path) => $errors->has($path));
    $translationsId = $getTranslationsId();
    $translationStatuses = $getTranslationStatuses();
    $primaryLanguage = $getPrimaryLanguage();
    $liveStatuses = [
        \App\Support\Multilingual\TranslationStatus::Complete,
        \App\Support\Multilingual\TranslationStatus::Fallback,
        \App\Support\Multilingual\TranslationStatus::Missing,
    ];
@endphp

<div
    class="fi-fo-multilanguage-field grid gap-y-2"
    x-data="{
        open: false,
        paths: @js($translationStatePaths),
        primaryPath: @js($getPrimaryStatePath()),
        isFilled(value) {
            if (value === null || value === undefined) {
                return false;
            }

            const element = new DOMParser().parseFromString(String(value), 'text/html').body;

            if (element.querySelector('img, iframe, video, audio, embed, object, svg, picture, table, hr')) {
                return true;
            }

            return element.textContent.replace(/\u00a0/g, ' ').trim() !== '';
        },
        get filled() {
            return this.paths.filter((path) => this.isFilled(this.$wire.get(path))).length;
        },
        status(path) {
            if (this.isFilled(this.$wire.get(path))) {
                return 'complete';
            }

            return this.isFilled(this.$wire.get(this.primaryPath)) ? 'fallback' : 'missing';
        },
    }"
>
    @if ($primaryContainer)
        <div data-multilanguage-primary>
            {{ $primaryContainer }}
        </div>
    @endif

    @if ($total > 0)
        <ul
            class="flex flex-wrap gap-x-4 gap-y-1 text-xs"
            aria-label="{{ __('general.translation_status') }}"
            data-multilanguage-status
        >
            @foreach ($translationStatuses as $translation)
                <li class="inline-flex items-center gap-x-1.5">
                    <span class="font-medium text-gray-700 dark:text-gray-200">{{ $translation['language'] }}</span>
                    @unless (in_array($translation['status'], $liveStatuses, true))
                        <span
                            class="inline-flex items-center gap-x-0.5 {{ $translation['status']->textClasses() }}"
                            data-status="{{ $translation['status']->value }}"
                        >
                            <x-filament::icon :icon="$translation['status']->icon()" class="h-4 w-4" />
                            {{ $translation['status']->label($primaryLanguage) }}
                        </span>
                    @else
                        @foreach ($liveStatuses as $status)
                            <span
                                class="inline-flex items-center gap-x-0.5 {{ $status->textClasses() }}"
                                data-status="{{ $status->value }}"
                                x-show="status(@js($translation['path'])) === @js($status->value)"
                                @if ($status !== $translation['status']) style="display: none" @endif
                            >
                                <x-filament::icon :icon="$status->icon()" class="h-4 w-4" />
                                {{ $status->label($primaryLanguage) }}
                            </span>
                        @endforeach
                    @endunless
                </li>
            @endforeach
        </ul>

        <div
            id="{{ $translationsId }}"
            class="grid gap-y-4 rounded-xl bg-gray-50 p-4 dark:bg-white/5"
            x-show="open"
            @if (! $hasTranslationErrors) style="display: none" @endif
        >
            @foreach ($childComponentContainers as $componentContainer)
                {{ $componentContainer }}
            @endforeach
        </div>
    @endif

    @if ($hasTranslationErrors)
        <span hidden wire:key="{{ \Illuminate\Support\Str::random() }}" x-init="$nextTick(() => open = true)" data-multilanguage-open-on-error></span>
    @endif
</div>
