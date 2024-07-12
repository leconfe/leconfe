<?php

namespace App\Frontend\Conference\Pages;

use App\Models\Announcement;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;
use Rahmanramsi\LivewirePageGroup\Pages\Page;

class AnnouncementList extends Page
{
    protected static string $view = 'frontend.conference.pages.announcement-list';

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => 'Home',
            'Announcements',
        ];
    }
    
    protected function getViewData(): array
    {
        return [
            'announcements' => Announcement::query()
                ->where('expires_at', '>', now()->startOfDay())
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
