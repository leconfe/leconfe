<?php

namespace App\Models\NavigationItemType;

use App\Models\NavigationMenuItem;

class Home extends BaseNavigationItemType
{
    public static function getId(): string
    {
        return 'home';
    }

    public static function getLabel(): string
    {
        return 'Home';
    }

    public static function getUrl(NavigationMenuItem $navigationMenuItem): string
    {
        if (app()->getCurrentScheduledConferenceId()) {
            return route('livewirePageGroup.scheduledConference.pages.home');
        }

        return route('livewirePageGroup.administration.pages.home');
    }
}
