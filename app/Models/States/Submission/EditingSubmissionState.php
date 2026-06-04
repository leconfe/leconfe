<?php

namespace App\Models\States\Submission;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Classes\Log;
use App\Events\Submissions\Accepted;
use App\Events\Submissions\Published;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\States\Submission\Concerns\CanDeclinePayment;
use App\Models\States\Submission\Concerns\CanWithdraw;

class EditingSubmissionState extends BaseSubmissionState
{
    use CanDeclinePayment;
    use CanWithdraw;

    public function sendToEditing(): void
    {
        // Repeating the current decision is allowed so editors can resend its notification.
    }

    public function acceptAbstract(): void
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
            event: 'submission-abstract-accepted',
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

    public function publish(): void
    {
        $publishedAt = $this->submission->published_at ?? now();
        $data = [
            'stage' => SubmissionStage::Proceeding,
            'status' => SubmissionStatus::Published,
            'published_at' => $publishedAt,
        ];

        $conference = app()->getCurrentConference();
        // get authors name split by semicolon
        if (! $this->submission->getMeta('copyright_holder')) {
            $data['meta']['copyright_holder'] = $conference->getCopyrightHolderForSubmission($this->submission);
        }

        if (! $this->submission->getMeta('copyright_year')) {
            $data['meta']['copyright_year'] = $conference->getCopyrightYearForSubmission($this->submission);
        }

        if (! $this->submission->getMeta('license_url')) {
            $data['meta']['license_url'] = $conference->getLicenseUrl();
        }

        SubmissionUpdateAction::run($data, $this->submission);

        Published::dispatch($this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_published')
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
