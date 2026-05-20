<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Login as WebsiteLogin;

class Login extends WebsiteLogin
{
    public function getViewData(): array
    {
        $allowRegistration = app()->getCurrentScheduledConference()->getMeta('allow_registration');

        return [
            'resetPasswordUrl' => route('livewirePageGroup.scheduledConference.pages.reset-password'),
            'registerUrl' => $allowRegistration
                ? route('livewirePageGroup.scheduledConference.pages.register')
                : null,
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.login'),
        ];
    }

    public function getRedirectUrl(): string
    {
        return route('filament.scheduledConference.pages.dashboard');
    }
}
