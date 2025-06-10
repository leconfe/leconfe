<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use App\Models\Stakeholder;
use App\Models\StakeholderLevel;
use App\Models\StaticPage;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class Home extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.home';

    public function mount() {}

    protected function getViewData(): array
    {
        return [
            'homepage' => StaticPage::getHome(),
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
