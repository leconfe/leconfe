@php
    $contentLocales = app(App\Support\Multilingual\ContentLocales::class);
@endphp

@if ($contentLocales->isMultilingual())
    @php
        $currentLocale = app()->getLocale();
        $setLocaleRoute = match (true) {
            app()->getCurrentScheduledConference() !== null => 'livewirePageGroup.scheduledConference.pages.set-locale',
            app()->getCurrentConference() !== null => 'livewirePageGroup.conference.pages.set-locale',
            default => 'livewirePageGroup.website.pages.set-locale',
        };
    @endphp

    <nav {{ $attributes->class(['language-switcher relative']) }} data-language-switcher aria-label="{{ __('general.switch_language') }}">
        <div
            x-data="{ open: false }"
            x-on:mouseleave="open = false"
            x-on:keydown.escape.window="open = false"
        >
            <button
                type="button"
                x-ref="button"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                aria-haspopup="true"
                class="navigation-menu-item btn btn-ghost no-animation btn-sm rounded-lg inline-flex items-center justify-center gap-1 px-3 transition-colors focus:outline-none disabled:opacity-50 disabled:pointer-events-none w-max ease-out duration-300"
            >
                <x-heroicon-m-language class="h-4 w-4" aria-hidden="true" />
                <span class="sr-only">{{ __('general.current_language') }}</span>
                <span lang="{{ str_replace('_', '-', $currentLocale) }}">{{ $contentLocales->label($currentLocale) }}</span>
                <x-heroicon-m-chevron-down class="transition relative top-[1px] h-3 w-3" x-bind:class="{ '-rotate-180': open }" aria-hidden="true" />
            </button>
            <div
                x-show="open"
                x-transition
                x-anchor.bottom-end="$refs.button"
                x-cloak
                class="navbar-dropdown-content z-[60] text-gray-800"
            >
                <ul class="flex flex-col divide-y mt-1 min-w-[12rem] bg-white rounded-md shadow-md">
                    @foreach ($contentLocales->ordered() as $locale)
                        <li>
                            <a
                                href="{{ route($setLocaleRoute, ['lang' => $locale]) }}"
                                lang="{{ str_replace('_', '-', $locale) }}"
                                hreflang="{{ str_replace('_', '-', $locale) }}"
                                @if ($locale === $currentLocale) aria-current="true" @endif
                                @class([
                                    'first:rounded-t-md last:rounded-b-md relative flex hover:bg-base-content/10 items-center justify-between py-2 px-4 text-sm outline-none transition-colors gap-4 w-full',
                                    'font-semibold text-primary' => $locale === $currentLocale,
                                ])
                            >
                                <span>{{ $contentLocales->autonym($locale) }}</span>
                                @if ($locale === $currentLocale)
                                    <x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </nav>
@endif
