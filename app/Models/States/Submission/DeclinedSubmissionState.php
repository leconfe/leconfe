<?php

namespace App\Models\States\Submission;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Classes\Log;
use App\Events\Submissions\Accepted;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;

class DeclinedSubmissionState extends BaseSubmissionState
{
    public function acceptAbstract(): void
    {
        SubmissionUpdateAction::run([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ], $this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_send_to_review'),
            event : 'submission-abstract-accepted',
        )
            ->by(auth()->user())
            ->save();
    }

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

    public function sendToEditing(): void
    {
        SubmissionUpdateAction::run([
            'revision_required' => false,
            'stage' => SubmissionStage::Editing,
            'status' => SubmissionStatus::Editing,
        ], $this->submission);

        Accepted::dispatch($this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_send_to_editing')
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
            description: __('general.submission_revision_required')
        )
            ->by(auth()->user())
            ->save();
    }
}
