<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use App\Models\Announcement;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class Announcements extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.announcement-list';

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.announcement'),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'announcements' => Announcement::query()
                ->where('expires_at', '>', now()->startOfDay())
                ->orWhereNull('expires_at')
                ->orderBy('created_at', 'desc')
                ->with('meta')
                ->get(),
        ];
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();

        Route::get('announcements', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
