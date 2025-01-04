<?php

namespace App\Models\NavigationItemType;

use App\Frontend\ScheduledConference\Pages\Contact as ContactPage;
use App\Models\NavigationMenuItem;

class Contact extends BaseNavigationItemType
{
    public static function getId(): string
    {
        return 'contact';
    }

    public static function getLabel(): string
    {
        return __('general.contact');
    }

    public static function getIsDisplayed(NavigationMenuItem $navigationMenuItem): bool
    {
        $currentScheduledConference = app()->getCurrentScheduledConference();

        return $currentScheduledConference && ($currentScheduledConference->getMeta('mailing_address') || $currentScheduledConference->getMeta('principal_contact_name'));
    }

    public static function getUrl(NavigationMenuItem $navigationMenuItem): string
    {
        if (app()->getCurrentScheduledConferenceId()) {
            return route(ContactPage::getRouteName('scheduledConference'), ['serie' => app()->getCurrentScheduledConference()]);
        }

        return '#';
    }
}
