<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Managers\PaymentManager;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ManageSubmissions;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function mount()
    {
        $submission = Submission::find(38);

        // $paymentManager = PaymentManager::get();
        // $queuePayment = $paymentManager->queue($submission->getMeta('title'), PaymentManager::TYPE_SUBMISSION_FEE, $submission->user, $submission, 500, 'usd');
        // dd($queuePayment);

        if (! auth()->user()->can('view', app()->getCurrentScheduledConference())) {
            return redirect()->to(ManageSubmissions::getUrl());
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('view', app()->getCurrentScheduledConference());
    }
}
