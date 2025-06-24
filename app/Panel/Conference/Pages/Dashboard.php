<?php

namespace App\Panel\Conference\Pages;

use App\Panel\Conference\Resources\ScheduledConferenceResource\Pages\ManageScheduledConferences;
use Filament\Pages\Dashboard as PagesDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends PagesDashboard
{
    public function mount()
    {
        return redirect()->to(ManageScheduledConferences::getUrl());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
