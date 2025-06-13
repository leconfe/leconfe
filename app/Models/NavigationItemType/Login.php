<?php

namespace App\Models\NavigationItemType;

use App\Models\NavigationMenuItem;

class Login extends BaseNavigationItemType
{
    public static function getId(): string
    {
        return 'login';
    }

    public static function getLabel(): string
    {
        return 'Login';
    }

    public static function getUrl(NavigationMenuItem $navigationMenuItem): string
    {
        if (app()->getCurrentScheduledConferenceId()) {
            return route('livewirePageGroup.scheduledConference.pages.login');
        }

        return route('livewirePageGroup.website.pages.login');
    }

    public static function getIsDisplayed(NavigationMenuItem $navigationMenuItem): bool
    {
        return ! auth()->check();
    }
}
