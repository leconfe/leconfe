<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\Enums\UserRole;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ManageSubmissions;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -10;

    public function mount()
    {
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
