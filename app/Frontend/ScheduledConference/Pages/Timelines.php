<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use App\Models\Timeline;

class Timelines extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.timelines';

    public function mount()
    {
        //
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.timelines'),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'timelines' => Timeline::query()
                ->where('hide', false)
                ->orderBy('date')
                ->with('translate')
                ->get(),
        ];
    }
}
