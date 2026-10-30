<?php

namespace App\Frontend\Website\Pages;

use App\Models\StaticPage as StaticPageModel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class StaticPage extends Page
{
    protected static string $view = 'frontend.website.pages.static-page';

    public StaticPageModel $staticPage;

    public function mount() {}

    public function getTitle(): string|Htmlable
    {
        return $this->staticPage->translated('title');
    }

    protected function getViewData(): array
    {
        return [
            'title' => $this->staticPage->translated('title'),
            'content' => $this->staticPage->translated('content'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => 'Home',
            $this->staticPage->translated('title'),
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
