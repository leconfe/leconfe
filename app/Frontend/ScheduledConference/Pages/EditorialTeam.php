<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;

class EditorialTeam extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.editorial-team';

    public function mount() {}

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.editorial_team'),
        ];
    }

    public function getViewData(): array
    {
        return [
            'editorialTeam' => app()->getCurrentScheduledConference()?->translated('editorial_team'),
        ];
    }
}
