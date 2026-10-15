<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class About extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.about';

    public function mount() {}

    public function getTitle(): string|Htmlable
    {
        return __('general.about_the_conference');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.about'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'about' => app()->getCurrentScheduledConference()?->translated('about'),
        ];
    }
}
