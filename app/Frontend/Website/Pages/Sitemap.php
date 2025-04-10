<?php

namespace App\Frontend\Website\Pages;

use App\Frontend\ScheduledConference\Pages as ScheduledConferencePages;
use App\Frontend\Website\Pages\Page;
use App\Http\Middleware\RedirectToScheduledConference;
use App\Models\Announcement;
use App\Models\Conference;
use App\Models\Enums\ScheduledConferenceState;
use App\Models\Proceeding;
use App\Models\ScheduledConference;
use App\Models\StaticPage;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap as SpatieSitemap;
use Spatie\Sitemap\Tags\Url;

class Sitemap extends Page
{
    protected static string|array $withoutRouteMiddleware = [
        RedirectToScheduledConference::class,
    ];

    public function __invoke()
    {
        // $sitemap = Cache::remember(
        //     'sitemap_'.app()->getCurrentConferenceId(),
        //     Carbon::now()->addMinutes(30),
        //     fn () => $this->generateSitemap(),
        // );

        $sitemap = $this->generateSitemap();
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
