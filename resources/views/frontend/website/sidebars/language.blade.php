@if ($contentLocales->isMultilingual())
    <x-website::sidebar class="sidebar-language sidebar-menu" :id="$id">
        <h2 class="text-heading">{{ __('general.language') }}</h2>
        <nav class="card card-compact bg-white border" aria-label="{{ __('general.switch_language') }}">
            <div class="card-body">
                <ul>
                    @foreach ($contentLocales->ordered() as $locale)
                        <li>
                            <a
                                href="{{ route($setLocaleRoute, ['lang' => $locale]) }}"
                                hreflang="{{ str_replace('_', '-', $locale) }}"
                                @if ($locale === $currentLocale) aria-current="true" @endif
                                @class(['text-primary font-semibold' => $locale === $currentLocale])
                            >
                                <span class="flex items-center justify-between gap-4">
                                    <span>
                                        @if ($locale === $currentLocale)
                                            <span class="sr-only">{{ __('general.current_language') }}</span>
                                        @endif
                                        <span lang="{{ str_replace('_', '-', $locale) }}">{{ $contentLocales->autonym($locale) }}</span>
                                    </span>
                                    @if ($locale === $currentLocale)
                                        <x-heroicon-m-check class="h-4 w-4 shrink-0" aria-hidden="true" />
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </nav>
    </x-website::sidebar>
@endif
