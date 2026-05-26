<?php

namespace App\Services\Billing;

use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Payment;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Notifications\SubmissionPayment;

class SubmissionBillingNotifier
{
    public const PAYMENT_META_AUTO_NOTIFIED_AT = 'submission_invoice_notified_at';

    public function isSubmissionPaymentAvailable(Submission $submission, ?ScheduledConference $scheduledConference = null): bool
    {
        if (! $this->isSubmissionBillingStageReached($submission, $scheduledConference)) {
            return false;
        }

        return ! in_array($submission->status, [
            SubmissionStatus::Declined,
            SubmissionStatus::Withdrawn,
        ], true);
    }

    public function isSubmissionBillingStageReached(Submission $submission, ?ScheduledConference $scheduledConference = null): bool
    {
        $scheduledConference ??= $this->resolveScheduledConference($submission);

        if (! $scheduledConference || ! $scheduledConference->isSubmissionPaymentEnabled()) {
            return false;
        }

        $currentStage = $submission->stage;
        $triggerStage = $scheduledConference->getSubmissionBillingStage();

        if (! $currentStage) {
            return false;
        }

        return $this->isStageReached($currentStage, $triggerStage);
    }

    public function maybeNotifyForSubmission(Submission $submission): bool
    {
        $payment = Payment::withoutGlobalScopes()
            ->where('model_type', Submission::class)
            ->where('model_id', $submission->getKey())
            ->first();

        if (! $payment || $payment->isPaid() || ! $submission->user) {
            return false;
        }

        if ($payment->getMeta(self::PAYMENT_META_AUTO_NOTIFIED_AT)) {
            return false;
        }

        if (! $this->isSubmissionPaymentAvailable($submission)) {
            return false;
        }

        $scheduledConference = $this->resolveScheduledConference($submission);
        if ($scheduledConference && ! $scheduledConference->isSubmissionPaymentAutoNotify()) {
            return false;
        }

        $payment->ensureInvoice();
        $submission->setRelation('payment', $payment->refresh());
        $submission->user->notify(new SubmissionPayment($submission));
        $payment->setMeta(self::PAYMENT_META_AUTO_NOTIFIED_AT, now()->toDateTimeString());

        return true;
    }

    protected function resolveScheduledConference(Submission $submission): ?ScheduledConference
    {
        return app()->getCurrentScheduledConference() ?: $submission->scheduledConference;
    }

    protected function isStageReached(SubmissionStage $currentStage, SubmissionStage $triggerStage): bool
    {
        return $this->getStageOrder($currentStage) >= $this->getStageOrder($triggerStage);
    }

    protected function getStageOrder(SubmissionStage $stage): int
    {
        return match ($stage) {
            SubmissionStage::Wizard => 0,
            SubmissionStage::CallforAbstract => 1,
            SubmissionStage::Payment => 2,
            SubmissionStage::PeerReview => 2,
            SubmissionStage::Presentation => 3,
            SubmissionStage::Editing => 4,
            SubmissionStage::Proceeding => 5,
        };
    }
}
