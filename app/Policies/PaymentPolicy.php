<?php

namespace App\Policies;

use App\Managers\PaymentManager;
use App\Models\Payment;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\User;
use App\Services\Billing\SubmissionBillingNotifier;

class PaymentPolicy
{
    public function view(User $user, Payment $payment)
    {
        if ($user->can('Payment:view')) {
            return true;
        }

        if (!$payment->user?->is($user)) {
            return false;
        }

        if ($payment->type !== PaymentManager::TYPE_SUBMISSION_FEE) {
            return true;
        }

        $submission = Submission::withoutGlobalScopes()->find($payment->model_id);

        if (!$submission) {
            return false;
        }

        $scheduledConference = ScheduledConference::withoutGlobalScopes()->find($payment->scheduled_conference_id);

        if (!$scheduledConference || !$submission->stage) {
            return false;
        }

        return app(SubmissionBillingNotifier::class)->isSubmissionPaymentAvailable($submission, $scheduledConference);
    }

    public function viewAny(User $user)
    {
        if ($user->can('Payment:viewAny')) {
            return true;
        }
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        if ($user->can('Payment:create')) {
            return true;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payment $payment)
    {
        if ($user->can('Payment:update')) {
            return true;
        }
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payment $payment)
    {
        if ($user->can('Payment:delete')) {
            return true;
        }
    }

    public function setUnpaid(User $user, Payment $payment)
    {
        if ($user->can('Payment:setUnpaid')) {
            return true;
        }
    }

    public function registerParticipant(User $user)
    {
        if ($user->can('Payment:registerParticipant')) {
            return true;
        }
    }
}
