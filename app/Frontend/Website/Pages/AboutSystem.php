<?php

namespace App\Frontend\Website\Pages;

class AboutSystem extends Page
{
    protected static string $view = 'frontend.website.pages.about-system';

    public function mount() {}

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => 'Home',
            'About System',
        ];
    }

    public function getViewData(): array
    {
        return [
            'name' => app()->getSite()->translated('name'),
            'version' => app()->getInstalledVersion(),
        ];
    }
}
