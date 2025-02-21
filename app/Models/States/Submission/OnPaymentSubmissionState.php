<?php

namespace App\Models\States\Submission;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Classes\Log;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\States\Submission\Concerns\CanDeclinePayment;
use App\Models\States\Submission\Concerns\CanWithdraw;

/**
 * @deprecated
 */
class OnPaymentSubmissionState extends BaseSubmissionState
{
    use CanDeclinePayment;
    use CanWithdraw;

    public function approvePayment(): void
    {
        SubmissionUpdateAction::run([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ], $this->submission);

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_payment_approved'),
            event : 'submission-payment-approved',
        )
            ->by(auth()->user())
            ->save();
    }

    public function decline(): void
    {
        SubmissionUpdateAction::run([
            'stage' => SubmissionStage::CallforAbstract,
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
}
