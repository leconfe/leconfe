<?php

namespace App\Frontend\Website\Pages;

use App\Models\StaticPage as StaticPageModel;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class StaticPage extends Page
{
    protected static string $view = 'frontend.website.pages.static-page';

    public StaticPageModel $staticPage;

    public function mount() {}

    protected function getViewData(): array
    {
        return [
            'title' => $this->staticPage->title,
            'content' => $this->staticPage->getMeta('content'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => 'Home',
            $this->staticPage->title,
        ];
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();

        Route::get('page/{staticPage:slug}', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
