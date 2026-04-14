<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Actions\Plugins\PluginPopulateDefaultSettingAction;
use App\Actions\ScheduledConferences\ScheduledConferencePing;
use App\Models\Enums\UserRole;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ManageSubmissions;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Cache;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -10;

    public function mount()
    {
        app()->getCurrentScheduledConference()->ping();

        if (! static::show()) {
            return redirect()->to(ManageSubmissions::getUrl());
        }
    }

    public static function show(): bool
    {
        return ! auth()->user()?->hasAnyRole([
            UserRole::TrackEditor,
            UserRole::Reviewer,
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::show();
    }
}
