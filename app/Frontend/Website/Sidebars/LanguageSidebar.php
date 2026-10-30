<?php

namespace App\Frontend\Website\Sidebars;

use App\Classes\Sidebar;
use App\Support\Multilingual\ContentLocales;
use Illuminate\Contracts\View\View;

class LanguageSidebar extends Sidebar
{
    public function getId(): string
    {
        return 'language';
    }

    public function getName(): string
    {
        return __('general.language');
    }

    public function render(): View
    {
        return view('frontend.website.sidebars.language', [
            'id' => $this->getId(),
            'contentLocales' => app(ContentLocales::class),
            'currentLocale' => app()->getLocale(),
            'setLocaleRoute' => match (true) {
                app()->getCurrentScheduledConference() !== null => 'livewirePageGroup.scheduledConference.pages.set-locale',
                app()->getCurrentConference() !== null => 'livewirePageGroup.conference.pages.set-locale',
                default => 'livewirePageGroup.website.pages.set-locale',
            },
        ]);
    }
}
