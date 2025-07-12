<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\Enums\UserRole;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ManageSubmissions;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'panel.scheduledConference.pages.dashboard';

    public function mount()
    {
        if(!static::show()){
            return redirect()->to(ManageSubmissions::getUrl());
        }
    }

    public static function show() : bool
    {
        return auth()->user()?->cannot('update', app()->getCurrentScheduledConference());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::show();
    }
}
