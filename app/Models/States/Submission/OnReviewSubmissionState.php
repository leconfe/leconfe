<?php

namespace App\Models\States\Submission;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Classes\Log;
use App\Events\Submissions\Accepted;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\States\Submission\Concerns\CanDeclinePayment;
use App\Models\States\Submission\Concerns\CanWithdraw;

class OnReviewSubmissionState extends BaseSubmissionState
{
    use CanDeclinePayment;
    use CanWithdraw;

    public function sendToPresentation(): void
    {
        SubmissionUpdateAction::run([
            'revision_required' => false,
            'skipped_review' => false,
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::OnPresentation,
        ], $this->submission);

        Accepted::dispatch($this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_send_to_presentation'),
            event: 'submission-send-to-presentation',
        )
            ->by(auth()->user())
            ->save();
    }

    public function decline(): void
    {
        SubmissionUpdateAction::run([
            'revision_required' => false,
            'status' => SubmissionStatus::Declined,
        ], $this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_declined'),
            event: 'submission-declined',
        )
            ->by(auth()->user())
            ->save();
    }

    public function requestRevision(): void
    {
        SubmissionUpdateAction::run([
            'revision_required' => true,
            'status' => SubmissionStatus::OnReview,
            'stage' => SubmissionStage::PeerReview,
        ], $this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_revision_required'),
            event: 'submission-revision-required',
        )
            ->by(auth()->user())
            ->save();
    }
}
