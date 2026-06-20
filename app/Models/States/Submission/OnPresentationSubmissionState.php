<?php

namespace App\Models\States\Submission;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Classes\Log;
use App\Events\Submissions\Accepted;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\States\Submission\Concerns\CanDeclinePayment;
use App\Models\States\Submission\Concerns\CanWithdraw;

class OnPresentationSubmissionState extends BaseSubmissionState
{
    use CanDeclinePayment;
    use CanWithdraw;

    public function sendToPresentation(): void
    {
        // Repeating the current decision is allowed so editors can resend its notification.
    }

    public function sendForReview(): void
    {
        SubmissionUpdateAction::run([
            'revision_required' => false,
            'skipped_review' => false,
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ], $this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_send_to_review'),
            event: 'submission-send-to-review',
        )
            ->by(auth()->user())
            ->save();
    }

    public function acceptAndSkipReview(): void
    {
        SubmissionUpdateAction::run([
            'skipped_review' => true,
            'revision_required' => false,
            'status' => SubmissionStatus::OnPresentation,
            'stage' => SubmissionStage::Presentation,
        ], $this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_accept_and_skip_review'),
            event: 'submission-skip-review',
        )
            ->by(auth()->user())
            ->save();
    }

    public function sendToEditing(): void
    {
        SubmissionUpdateAction::run([
            'revision_required' => false,
            'skipped_review' => false,
            'stage' => SubmissionStage::Editing,
            'status' => SubmissionStatus::Editing,
        ], $this->submission);

        Accepted::dispatch($this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_send_to_editing'),
            event: 'submission-send-to-editing',
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
            description: __('general.submission_declined')
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
