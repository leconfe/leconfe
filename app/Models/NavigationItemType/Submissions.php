<?php

namespace App\Models\NavigationItemType;

use App\Models\NavigationMenuItem;

class Submissions extends BaseNavigationItemType
{
    public static function getId(): string
    {
        return 'submissions';
    }

    public static function getLabel(): string
    {
        return 'Submissions';
    }

    public static function getIsDisplayed(NavigationMenuItem $navigationMenuItem): bool
    {
        return (bool) app()->getCurrentScheduledConferenceId();
    }

    public static function getUrl(NavigationMenuItem $navigationMenuItem): string
    {
        return route('livewirePageGroup.scheduledConference.pages.submissions');
    }
}
