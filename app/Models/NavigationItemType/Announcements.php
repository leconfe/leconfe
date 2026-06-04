<?php

namespace App\Models\NavigationItemType;

use App\Models\NavigationMenuItem;

class Announcements extends BaseNavigationItemType
{
    public static function getId(): string
    {
        return 'announcements';
    }

    public static function getLabel(): string
    {
        return 'Announcements';
    }

    public static function getIsDisplayed(NavigationMenuItem $navigationMenuItem): bool
    {
        return (bool) app()->getCurrentScheduledConferenceId();
    }

    public static function getUrl(NavigationMenuItem $navigationMenuItem): string
    {
        return route('livewirePageGroup.scheduledConference.pages.announcements');
    }
}
