<?php

namespace App\Frontend\Conference\Pages;

use App\Frontend\Website\Pages\Page;
use App\Http\Middleware\RedirectToScheduledConference;
use App\Models\Enums\ScheduledConferenceState;
use App\Models\ScheduledConference;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class Home extends Page
{
    protected static string $view = 'frontend.conference.pages.home';

    protected static string|array $routeMiddleware = [
        RedirectToScheduledConference::class
    ];

    public function mount() {}

    protected function getViewData(): array
    {
        $conference = app()->getCurrentConference();
        $upcomingScheduledConferences = ScheduledConference::query()
            ->with(['media', 'meta', 'conference'])
            ->where('state', ScheduledConferenceState::Published)
            ->orderBy('date_start', 'desc')
            ->get();

        $pastScheduledConferences = ScheduledConference::query()
            ->with(['media', 'meta', 'conference'])
            ->where('state', ScheduledConferenceState::Archived)
            ->orderBy('date_start', 'desc')
            ->get();

        $nextScheduledConference = ScheduledConference::query()
            ->with(['media', 'meta', 'conference'])
            ->where('state', ScheduledConferenceState::Current)
            ->first();

        return [
            'conference' => $conference,
            'upcomingScheduledConferences' => $upcomingScheduledConferences,
            'pastScheduledConferences' => $pastScheduledConferences,
            'nextScheduledConference' => $nextScheduledConference,
        ];
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get('/', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
