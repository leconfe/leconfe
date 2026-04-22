<?php

namespace App\Policies;

use App\Models\Enums\RegistrationPaymentState;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SubmissionPolicy
{
    public function create(User $user)
    {
        return true;
    }

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Submission $submission)
    {
        if ($user->is($submission->user)) {
            return true;
        }

        if ($submission->participants->where('user_id', $user->getKey())->isNotEmpty()) {
            return true;
        }

        if ($user->can('Submission:view')) {
            return true;
        }
    }

    public function update(User $user, Submission $submission)
    {
        if ($submission->status == SubmissionStatus::Published) {
            return false;
        }

        if ($user->can('Submission:update')) {
            return true;
        }
    }

    public function delete(User $user, Submission $submission)
    {
        // Only submission with status: withdrawn or declined can be deleted.
        if (! in_array($submission->status, [SubmissionStatus::Declined, SubmissionStatus::Withdrawn, SubmissionStatus::Incomplete])) {
            return false;
        }

        if ($user->can('Submission:delete')) {
            return true;
        }
    }

    public function assignReviewer(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Declined, SubmissionStatus::Withdrawn, SubmissionStatus::Published, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued])) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function editReviewer(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Declined, SubmissionStatus::Withdrawn, SubmissionStatus::Published, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued])) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function cancelReviewer(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Declined, SubmissionStatus::Withdrawn, SubmissionStatus::Published, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued])) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function emailReviewer(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Declined, SubmissionStatus::Withdrawn, SubmissionStatus::Published, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued])) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function reinstateReviewer(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Declined, SubmissionStatus::Withdrawn, SubmissionStatus::Published, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued])) {
            return false;
        }

        if ($user->can('Submission:reinstateReviewer')) {
            return true;
        }
    }

    public function declinePaper(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Withdrawn, SubmissionStatus::Declined, SubmissionStatus::Published, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued, SubmissionStatus::Declined])) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function uploadAbstract(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Declined, SubmissionStatus::Withdrawn, SubmissionStatus::Published])) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        if ($user->can('submitAs', Submission::class)) {
            return true;
        }

        if (! $submission->isParticipant($user)) {
            return false;
        }

        return true;
    }

    public function uploadPresentation(User $user, Submission $submission)
    {
        if (in_array($submission->status, [
            SubmissionStatus::Declined,
            SubmissionStatus::Withdrawn,
            SubmissionStatus::Published,
            SubmissionStatus::OnReview,
        ])) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        if ($user->can('Submission:uploadPresentation')) {
            return true;
        }
    }

    public function uploadPaper(User $user, Submission $submission)
    {

        if (in_array($submission->status, [SubmissionStatus::Declined, SubmissionStatus::Withdrawn, SubmissionStatus::Published, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued])) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        if ($user->can('Submission:uploadPaper')) {
            return true;
        }
    }

    public function uploadRevisionFiles(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Declined, SubmissionStatus::Withdrawn, SubmissionStatus::Published])) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        if ($user->can('Submission:uploadRevisionFiles')) {
            return true;
        }
    }

    public function acceptPaper(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Withdrawn, SubmissionStatus::Declined, SubmissionStatus::Published, SubmissionStatus::OnPresentation, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued])) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function declinePayment(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Withdrawn, SubmissionStatus::Published, SubmissionStatus::Queued])) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        if ($user->can('Submission:declinePayment')) {
            return true;
        }
    }

    public function approvePayment(User $user, Submission $submission)
    {
        if ($submission->stage != SubmissionStage::Payment || ($submission->status != SubmissionStatus::OnPayment && $submission->status != SubmissionStatus::PaymentDeclined)) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        if (! $submission->registration) {
            return false;
        }

        if ($user->can('Submission:approvePayment')) {
            return true;
        }
    }

    public function review(User $user, Submission $submission)
    {
        if (! in_array($submission->stage, [SubmissionStage::PeerReview, SubmissionStage::Presentation, SubmissionStage::Editing, SubmissionStage::Proceeding])) {
            return false;
        }

        if (! $submission->getReviewForUserInActiveRound($user)) {
            return false;
        }

        if ($user->can('Submission:review')) {
            return true;
        }
    }

    public function requestRevision(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Withdrawn, SubmissionStatus::Declined, SubmissionStatus::Published, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued])) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function skipReview(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Withdrawn, SubmissionStatus::Declined, SubmissionStatus::Published, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued])) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function sendToEditing(User $user, Submission $submission)
    {
        if ($submission->stage != SubmissionStage::Presentation) {
            return false;
        }

        if ($user->can('Submission:sendToEditing')) {
            return true;
        }
    }

    public function assignParticipant(User $user, Submission $submission)
    {
        if ($submission->status === SubmissionStatus::Withdrawn) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function editing(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Published, SubmissionStatus::Declined, SubmissionStatus::Withdrawn])) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function withdraw(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Withdrawn, SubmissionStatus::Declined])) {
            return false;
        }

        // Editors cannot withdraw submissions; they must wait for the author to request it..
        if (! filled($submission->withdrawn_reason)) {
            return false;
        }

        if ($user->can('Submission:withdraw')) {
            return true;
        }
    }

    public function requestWithdraw(User $user, Submission $submission)
    {
        // Only the author can request a withdrawal.
        if ($user->getKey() !== $submission->user->getKey()) {
            return false;
        }

        if (in_array($submission->status, [SubmissionStatus::Withdrawn, SubmissionStatus::Declined, SubmissionStatus::Published, SubmissionStatus::Editing])) {
            return false;
        }

        if ($submission->stage == SubmissionStage::Wizard) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        if ($user->can('Submission:requestWithdraw')) {
            return true;
        }
    }

    public function unpublish(User $user, Submission $submission)
    {
        if ($submission->status != SubmissionStatus::Published) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function publish(User $user, Submission $submission)
    {
        if ($submission->status != SubmissionStatus::Editing) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }

    public function cancelRegistration(User $user, Submission $submission)
    {
        if ($submission->registration->user->getKey() !== $user->getKey()) {
            return false;
        }

        if ($submission->registration->registrationPayment->state === RegistrationPaymentState::Paid->value) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        return true;
    }

    public function decideRegistration(User $user, Submission $submission)
    {
        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        if ($user->can('Submission:decideRegistration')) {
            return true;
        }
    }

    public function deleteRegistration(User $user, Submission $submission)
    {
        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        if ($user->can('Submission:deleteRegistration')) {
            return true;
        }
    }

    public function preview(User $user, Submission $submission)
    {
        $editorIds = $submission->participants()
            ->whereHas('role', fn (Builder $query) => $query->withoutGlobalScopes()->whereIn('name', [UserRole::ScheduledConferenceEditor, UserRole::TrackEditor]))
            ->pluck('user_id');

        if (in_array($user->getKey(), $editorIds->toArray())) {
            return true;
        }

        if ($user->can('Submission:preview')) {
            return true;
        }
    }

    public function submitAs(User $user)
    {
        if ($user->can('Submission:submitAs')) {
            return true;
        }

        return false;
    }

    public function actAsEditor(User $user, Submission $submission)
    {
        if ($user->can('submitAs', Submission::class) && ! $submission->isParticipantAuthor($user)) {
            return true;
        }

        return $submission->isParticipantEditor($user);
    }

    public function deleteFile(User $user, Submission $submission)
    {
        if (in_array($submission->status, [SubmissionStatus::Declined, SubmissionStatus::Withdrawn, SubmissionStatus::Published, SubmissionStatus::OnPayment, SubmissionStatus::PaymentDeclined, SubmissionStatus::Queued])) {
            return false;
        }

        if (filled($submission->withdrawn_reason)) {
            return false;
        }

        return $user->can('actAsEditor', $submission);
    }
}
