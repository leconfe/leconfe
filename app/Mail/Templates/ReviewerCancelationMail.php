<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Review;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class ReviewerCancelationMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public Log $log;

    public function __construct(Review $review)
    {
        $this->setAdditionalData([
            'Reviewer Name' => $review->user->fullName,
            'Conference Title' => $review->submission->scheduledConference->title,
            'Submission Title' => $review->submission->getMeta('title'),
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $review->submission]),
        ]);
        $this->log = Log::make(
            name: 'email',
            subject: $review->submission,
            description: __('general.email_sent', ['name' => 'Reviewer Canceled']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'You have been cancelled as a reviewer';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to reviewers when they are cancelled from a submission';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Reviewer Name }},</p>
            <p>We have decided to withdraw our request for you to review the submission, "{{ Submission Title }}," for {{ Conference Title }}.</p>
            <p>We apologize for any inconvenience and truly hope to have the opportunity to invite you to assist with the review process of future conferences.</p>
        HTML;
    }
}
