<?php

namespace App\Frontend\Website\Pages;

use App\Models\Conference;
use App\Models\Enums\ScheduledConferenceState;
use App\Models\Meta;
use App\Models\ScheduledConference;
use App\Models\Topic;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class Home extends Page
{
    use WithoutUrlPagination, WithPagination;

    protected static string $view = 'frontend.website.pages.home';

    public function getTitle(): string|Htmlable
    {
        return __('general.home');
    }

    protected function getViewData(): array
    {
        $featuredScheduledConferences = ScheduledConference::query()
            ->withoutGlobalScopes()
            ->with([
                'conference',
                'media',
                'meta',
            ])
            ->whereNotNull('featured')
            ->orderBy('featured', 'ASC')
            ->get();

        $scheduledConferences = ScheduledConference::query()
            ->withoutGlobalScopes()
            ->with([
                'conference',
                'media',
                'meta',
            ])
            ->whereIn('state', [
                ScheduledConferenceState::Archived,
                ScheduledConferenceState::Published,
                ScheduledConferenceState::Current,
            ])
            ->orderBy('date_start', 'DESC')
            ->get();

        return [
            'scheduledConferences' => $scheduledConferences,
            'featuredScheduledConferences' => $featuredScheduledConferences,
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
