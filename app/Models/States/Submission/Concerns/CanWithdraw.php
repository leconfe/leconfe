<?php

namespace App\Models\States\Submission\Concerns;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Classes\Log;
use App\Models\Enums\SubmissionStatus;

trait CanWithdraw
{
    public function withdraw(): void
    {
        SubmissionUpdateAction::run(['status' => SubmissionStatus::Withdrawn], $this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_withdrawn')
        )
            ->by(auth()->user())
            ->save();
    }
}
