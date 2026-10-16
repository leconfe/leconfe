@php
    $childComponentContainers = $getChildComponentContainers();
    $primaryContainer = array_shift($childComponentContainers);
    $translationStatePaths = $getTranslationStatePaths();
    $total = count($translationStatePaths);
    $hasTranslationErrors = collect($translationStatePaths)->contains(fn (string $path) => $errors->has($path));
    $translationsId = $getTranslationsId();
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
    x-on:focusin="if ($event.target.closest('[data-multilanguage-primary]') && ! $event.target.closest('[data-multilanguage-toggle]')) open = true"
>
    @if ($primaryContainer)
        <div data-multilanguage-primary>
            {{ $primaryContainer }}
        </div>
    @endif

    @if ($total > 0)
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
