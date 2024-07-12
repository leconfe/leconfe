<?php

namespace App\Providers;

use App\Http\Middleware\IdentifyConference;
use App\Http\Middleware\SetupConference;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Rahmanramsi\LivewirePageGroup\Facades\LivewirePageGroup;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class FrontendServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->resolving('livewire-page-group', function () {
            LivewirePageGroup::registerPageGroup(
                $this->websitePageGroup(PageGroup::make()),
            );
            LivewirePageGroup::registerPageGroup(
                $this->conferencePageGroup(PageGroup::make()),
            );
            LivewirePageGroup::registerPageGroup(
                $this->seriePageGroup(PageGroup::make()),
            );

            Livewire::addPersistentMiddleware([
                'web',
                IdentifyConference::class,
            ]);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/frontend/website/components'), 'website');
        Blade::anonymousComponentPath(resource_path('views/frontend/conference/components'), 'conference'); 
    }

    public function websitePageGroup(PageGroup $pageGroup): PageGroup
    {
        return $pageGroup
            ->id('website')
            ->path('')
            ->layout('frontend.website.components.layouts.app')
            ->bootUsing(function () {
            })
            ->middleware([
                'web',
            ], true)
            ->discoverPages(in: app_path('Frontend/Website/Pages'), for: 'App\\Frontend\\Website\\Pages');
    }

    public function conferencePageGroup(PageGroup $pageGroup): PageGroup
    {
        return $pageGroup
            ->id('conference')
            ->path('{conference:path}')
            ->layout('frontend.website.components.layouts.app')
            ->bootUsing(function () {
            })
            ->middleware([
                'web',
                IdentifyConference::class,
                SetupConference::class,
            ], true)
            ->discoverPages(in: app_path('Frontend/Conference/Pages'), for: 'App\\Frontend\\Conference\\Pages');
    }

    public function seriePageGroup(PageGroup $pageGroup): PageGroup
    {
        return $pageGroup
            ->id('series')
            ->path('{conference:path}/series/{serie:path}')
            ->layout('frontend.website.components.layouts.app')
            ->bootUsing(function () {

            })
            ->middleware([
                'web',
                IdentifyConference::class,
                SetupConference::class,
            ], true)
            ->discoverPages(in: app_path('Frontend/Conference/Pages'), for: 'App\\Frontend\\Conference\\Pages');
    }
}
