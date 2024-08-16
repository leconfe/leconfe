<?php

namespace App\Models\States\Submission;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Classes\Log;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;

class IncompleteSubmissionState extends BaseSubmissionState
{
    public function fulfill(): void
    {
        SubmissionUpdateAction::run([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ], $this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_created')
        )
            ->by(auth()->user())
            ->save();
    }
}
