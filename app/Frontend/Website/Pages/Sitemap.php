<?php

namespace App\Frontend\Website\Pages;

use App\Application;
use App\Http\Middleware\RedirectToScheduledConference;
use App\Models\Conference;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap as SpatieSitemap;
use Spatie\Sitemap\Tags\Url;

class Sitemap extends Page
{
    public function __invoke()
    {
        $sitemap = Cache::remember(
            'sitemap_'.Application::CONTEXT_WEBSITE,
            Carbon::now()->addHour(4),
            fn () => $this->generateSitemap(),
        );

        return response($sitemap->render(), 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function generateSitemap(): SpatieSitemap
    {
        $sitemap = SpatieSitemap::create();

        Conference::query()
            ->lazy()
            ->each(fn ($conference) => $sitemap->add(
                Url::create(route('livewirePageGroup.conference.pages.sitemap', ['conference' => $conference->path]))
            ));

        return $sitemap;
    }
}
