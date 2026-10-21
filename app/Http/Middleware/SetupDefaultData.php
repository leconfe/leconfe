<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetupDefaultData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isInstalled()) {
            return $next($request);
        }

        View::share('theme', app()->getCurrentTheme());

        $currentScheduledConference = app()->getCurrentScheduledConference();
        if ($currentScheduledConference) {
            $this->setupScheduledConference($request, $currentScheduledConference);

            return $next($request);
        }

        if ($currentConference = app()->getCurrentConference()) {
            $this->setupConference($request, $currentConference);

            return $next($request);
        }

        $this->setupSite();

        return $next($request);
    }

    protected function setupSite()
    {
        $site = app()->getSite();

        View::share('site', $site);
        View::share('homeUrl', route('livewirePageGroup.website.pages.home'));
        View::share('headerLogo', $site->getFirstMedia('logo')?->getAvailableUrl(['thumb', 'thumb-xl']));
        View::share('favicon', $site->getFirstMedia('favicon')?->getAvailableUrl(['tenant', 'thumb', 'thumb-xl']));
        View::share('styleSheet', $site->getFirstMediaUrl('styleSheet'));
    }

    protected function setupConference(Request $request, $currentConference)
    {
        View::share('currentConference', $currentConference);
        View::share('homeUrl', route('livewirePageGroup.conference.pages.home'));
        View::share('headerLogo', $currentConference->getFirstMedia('logo')?->getAvailableUrl(['thumb', 'thumb-xl']));
        View::share('favicon', $currentConference->getFirstMedia('favicon')?->getAvailableUrl(['tenant', 'thumb', 'thumb-xl']));
        View::share('styleSheet', $currentConference->getFirstMediaUrl('styleSheet'));
    }

    protected function setupScheduledConference(Request $request, $currentScheduledConference)
    {
        View::share('currentConference', app()->getCurrentConference());
        View::share('currentScheduledConference', $currentScheduledConference);
        View::share('homeUrl', route('livewirePageGroup.scheduledConference.pages.home'));
        View::share('headerLogo', $currentScheduledConference->getFirstMedia('logo')?->getAvailableUrl(['thumb', 'thumb-xl']));
        View::share('favicon', $currentScheduledConference->getFirstMedia('favicon')?->getAvailableUrl(['tenant', 'thumb', 'thumb-xl']));
        View::share('styleSheet', $currentScheduledConference->getFirstMediaUrl('styleSheet'));
    }
}
