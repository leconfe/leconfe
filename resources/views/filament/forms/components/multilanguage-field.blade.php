@php
    $childComponentContainers = $getChildComponentContainers();
    $primaryContainer = array_shift($childComponentContainers);
    $translationStatePaths = $getTranslationStatePaths();
    $total = count($translationStatePaths);
    $hasTranslationErrors = collect($translationStatePaths)->contains(fn (string $path) => $errors->has($path));
    $filledCount = collect($translationStatePaths)->reject(fn (string $path) => \App\Support\Multilingual\ContentLocales::isBlank(data_get($getLivewire(), $path)))->count();
    $translationsId = 'translations-' . md5(implode('|', $translationStatePaths));
@endphp

<div
    class="fi-fo-multilanguage-field grid gap-y-2"
    x-data="{
        open: @js($hasTranslationErrors),
        paths: @js($translationStatePaths),
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
    }"
    x-on:focusin="if ($event.target.closest('[data-multilanguage-primary]')) open = true"
>
    @if ($primaryContainer)
        <div data-multilanguage-primary>
            {{ $primaryContainer }}
        </div>
    @endif

    @if ($total > 0)
        <div>
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md text-sm font-medium text-primary-600 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:text-primary-400"
                aria-controls="{{ $translationsId }}"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                x-on:click="open = ! open"
                data-multilanguage-toggle
            >
                <x-filament::icon
                    icon="heroicon-m-language"
                    class="h-4 w-4"
                />
                <span
                    x-text="@js(__('general.translations_filled', ['filled' => '__FILLED__', 'total' => $total])).replace('__FILLED__', filled)"
                >{{ __('general.translations_filled', ['filled' => $filledCount, 'total' => $total]) }}</span>
                <span class="sr-only" x-text="open ? @js(__('general.hide_translations')) : @js(__('general.show_translations'))"></span>
            </button>
        </div>

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
</div>
