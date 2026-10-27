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
        $this->setTranslatableData('Submission Title', $review->submission, 'title');
        $this->setTranslatableData('Conference Title', $review->submission->scheduledConference, 'title');
        $this->log = Log::make(
            name: 'email',
            subject: $review->submission,
            description: __('general.email_sent', ['name' => 'Reviewer Canceled']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.reviewer_cancelation.subject');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to reviewers when they are cancelled from a submission';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.reviewer_cancelation.body');
    }
}
