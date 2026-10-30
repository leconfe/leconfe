<?php

namespace App\Frontend\Conference\Pages;

use App\Frontend\Website\Pages\AboutSystem as Page;

class AboutSystem extends Page
{
    public function getViewData(): array
    {
        return [
            'name' => app()->getCurrentConference()->translated('name'),
            'version' => app()->getInstalledVersion(),
        ];
    }
}
