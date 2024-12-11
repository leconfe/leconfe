<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use App\Models\Media;

class PublisherLibrary extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.publisher-library';

    public function mount()
    {
        //
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.publisher_library'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'publisherLibraries' => app()->getCurrentScheduledConference()->getMedia('publisher-library')->filter(fn (Media $media) => $media->getCustomProperty('is_public')),
        ];
    }
}
