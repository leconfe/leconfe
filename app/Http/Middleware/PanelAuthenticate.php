<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;

class PanelAuthenticate extends Authenticate
{
    protected function redirectTo($request): ?string
    {
        if (app()->getCurrentScheduledConference()) {
            return route('livewirePageGroup.scheduledConference.pages.login');
        }

        return route('livewirePageGroup.website.pages.login');
    }
}
