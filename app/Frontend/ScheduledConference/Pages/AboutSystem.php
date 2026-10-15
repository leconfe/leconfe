<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\AboutSystem as Page;

class AboutSystem extends Page
{
    public function getViewData(): array
    {
        return [
            'name' => app()->getCurrentScheduledConference()->translated('title'),
            'version' => app()->getInstalledVersion(),
        ];
    }
}
