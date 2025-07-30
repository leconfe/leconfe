<?php

namespace App\Models\NavigationItemType;

use App\Models\NavigationMenuItem;
use App\Models\Proceeding;

class Proceedings extends BaseNavigationItemType
{
    public static function getId(): string
    {
        return 'proceedings';
    }

    public static function getLabel(): string
    {
        return 'Proceedings';
    }

    public static function getIsDisplayed(NavigationMenuItem $navigationMenuItem): bool
    {
        $proceeding = Proceeding::query();

        if(app()->getCurrentConferenceId()){
            return $proceeding->count() > 0;
        }
        return $proceeding->withoutGlobalScopes()->count() > 0;
    }

    public static function getUrl(NavigationMenuItem $navigationMenuItem): string
    { 
        if(app()->getCurrentConferenceId()){
            return route('livewirePageGroup.conference.pages.proceedings');
        }

        return route('livewirePageGroup.website.pages.proceedings');
    }
}
