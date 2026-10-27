<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Review;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Carbon\Carbon;

class ReviewerInvitationMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public string $name;

    public string $submissionTitle;

    public string $responseDueDate;

    public string $reviewDueDate;

    public string $loginLink;

    public array $logDetail;

    public Log $log;

    public function __construct(Review $review)
    {
        $submission = $review->submission;

        $this->setAdditionalData([
            'Reviewer Name' => $review->user->fullName,
            'Conference Title' => $submission->scheduledConference->title,
            'Submission Title' => $submission->getMeta('title'),
            'Submission Invitation URL' => SubmissionResource::getUrl('reviewer-invitation', ['record' => $submission]),
            'Response Due Date' => Carbon::parse($review->getMeta('response_due_date'))->format('d F Y'),
            'Review Due Date' => Carbon::parse($review->getMeta('review_due_date'))->format('d F Y'),
        ]);
        $this->setTranslatableData('Submission Title', $submission, 'title');
        $this->setTranslatableData('Conference Title', $submission->scheduledConference, 'title');

        $this->log = Log::make(
            name: 'email',
            subject: $review->submission,
            description: __('general.email_sent', ['name' => 'Reviewer Invitation']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.reviewer_invitation.subject');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to reviewers when they are assigned to a submission';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.reviewer_invitation.body');
    }
}
