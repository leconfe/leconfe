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
        
        $this->log = Log::make(
            name: 'email',
            subject: $review->submission,
            description: __('general.email_sent', ['name' => 'Reviewer Invitation']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'You have been assigned as a reviewer';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to reviewers when they are assigned to a submission';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Reviewer Name }},</p>

            <p>I would like to invite you to review the paper "{{ Submission Title }}," which has been submitted to {{ Conference Title }}.</p>

            <p>Please log into the conference website by {{ Response Due Date }} to indicate whether you are able to undertake the review. Through the site, you can access the submission, record your review, and provide your recommendation: {{ Submission Invitation URL }}</p>

            <p>The completed review is due by {{ Review Due Date }}.</p>

            <p>Thank you very much for considering this request.</p>
        HTML;
    }
}
