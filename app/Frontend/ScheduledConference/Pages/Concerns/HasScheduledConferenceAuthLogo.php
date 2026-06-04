<?php

namespace App\Frontend\ScheduledConference\Pages\Concerns;

use Illuminate\Support\Facades\Vite;

trait HasScheduledConferenceAuthLogo
{
    public function hasLogo(): bool
    {
        return false;
    }

    public function getAuthLogoUrl(): string
    {
        return app()->getCurrentScheduledConference()
            ?->getFirstMedia('logo')
            ?->getAvailableUrl(['thumb', 'thumb-xl'])
            ?? Vite::asset('resources/assets/images/logo.png');
    }

    public function getAuthLogoAltText(): string
    {
        return app()->getCurrentScheduledConference()?->title ?? config('app.name', 'Leconfe');
    }

    public function getAuthLogoHomeUrl(): string
    {
        return app()->getCurrentScheduledConference()?->getHomeUrl() ?? url('/');
    }
}
