<?php

namespace App\Panel\ScheduledConference\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'panel.scheduledConference.pages.dashboard';

    public function mount() {}
}
