<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use App\Models\Announcement;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class AnnouncementPage extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.announcement';

    public Announcement $announcement;

    public function getTitle(): string|Htmlable
    {
        return $this->announcement->translated('title');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            route(Announcements::getRouteName()) => __('general.announcement'),
            $this->announcement->translated('title'),
        ];
    }

    protected function getViewData(): array
    {
        return [];
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();

        Route::get('announcements/view/{announcement}', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
