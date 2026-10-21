<?php

namespace Tests\Feature\Multilingual\Concerns;

use App\Models\Conference;
use App\Models\ScheduledConference;

trait InteractsWithContentLanguages
{
    /**
     * Enable content languages the way Administration > Language settings does: on the site only.
     *
     * @param  array<int, string>  $languages
     */
    protected function enableLanguages(array $languages, string $default = 'en'): void
    {
        app()->getSite()->setManyMeta([
            'settings_languages' => $languages,
            'settings_default_language' => $default,
        ]);
    }

    protected function makeConference(string $name = 'Annual Conference', string $path = 'annual'): Conference
    {
        return Conference::query()->create([
            'name' => $name,
            'path' => $path,
        ]);
    }

    protected function makeScheduledConference(Conference $conference, string $title = 'Annual Conference 2026', string $path = '2026'): ScheduledConference
    {
        return ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => $title,
            'path' => $path,
            'is_published' => true,
            'date_start' => now()->addMonth(),
            'date_end' => now()->addMonth()->addDays(2),
        ]);
    }

    protected function useConference(Conference $conference, ?ScheduledConference $scheduledConference = null): void
    {
        app()->setCurrentConferenceId($conference->getKey());

        if ($scheduledConference) {
            app()->setCurrentScheduledConferenceId($scheduledConference->getKey());
        }
    }
}
