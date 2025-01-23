<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Managers\PaymentManager;
use App\Models\PaymentFee;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ManageSubmissions;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function mount()
    {
        return redirect()->to(ManageSubmissions::getUrl());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
