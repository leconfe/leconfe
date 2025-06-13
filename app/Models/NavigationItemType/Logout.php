<?php

namespace App\Models\NavigationItemType;

use App\Models\NavigationMenuItem;

class Logout extends BaseNavigationItemType
{
    public static function getId(): string
    {
        return 'logout';
    }

    public static function getLabel(): string
    {
        return 'Logout';
    }

    public static function getIsDisplayed(NavigationMenuItem $navigationMenuItem): bool
    {
        return auth()->check();
    }

    public static function getUrl(NavigationMenuItem $navigationMenuItem): string
    {
        if (app()->getCurrentScheduledConferenceId()) {
            return route('livewirePageGroup.scheduledConference.pages.logout');
        }

        return route('livewirePageGroup.website.pages.logout');
    }
}
