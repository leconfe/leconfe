@php
    $primary = $locales->label($readiness->primaryLocale);
    $conferenceLocale = $locales->default();
@endphp

<div class="space-y-3 text-sm text-gray-700 dark:text-gray-300" data-language-readiness>
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
        {{ __('general.language_readiness_heading') }}
    </h3>

    <ul class="space-y-2">
        @if ($readiness->isPaper)
            <li>
                {{ __('general.language_readiness_paper_language', ['language' => $primary]) }}
                @if ($readiness->primaryLocale !== $conferenceLocale)
                    <span class="block text-gray-500 dark:text-gray-400">
                        {{ __('general.language_readiness_paper_language_differs', ['conference' => $locales->label($conferenceLocale)]) }}
                    </span>
                @endif
            </li>
        @endif

        @foreach ($readiness->missing as $locale => $fields)
            <li @class(['text-warning-600 dark:text-warning-400' => $fields])>
                @if ($fields)
                    {{ __('general.language_readiness_fallback', ['language' => $locales->label($locale), 'fields' => implode(', ', $fields), 'fallback' => $primary]) }}
                @else
                    {{ __('general.language_readiness_complete', ['language' => $locales->label($locale)]) }}
                @endif
            </li>
        @endforeach

        @foreach ($readiness->incompletePapers as $locale => $incomplete)
            <li @class(['text-warning-600 dark:text-warning-400' => $incomplete])>
                @if ($incomplete)
                    {{ trans_choice('general.language_readiness_papers_incomplete', $incomplete, ['language' => $locales->label($locale), 'incomplete' => $incomplete]) }}
                @else
                    {{ __('general.language_readiness_papers_complete', ['language' => $locales->label($locale)]) }}
                @endif
            </li>
        @endforeach

        @if ($readiness->emailLocale)
            <li @class(['text-warning-600 dark:text-warning-400' => $readiness->emailContentLocale !== $readiness->emailLocale])>
                {{ __('general.language_readiness_email', ['language' => $locales->label($readiness->emailLocale)]) }}
                @if ($readiness->emailContentLocale !== $readiness->emailLocale)
                    {{ __('general.language_readiness_email_fallback', ['fallback' => $locales->label($readiness->emailContentLocale)]) }}
                @endif
            </li>
        @endif
    </ul>
</div>
