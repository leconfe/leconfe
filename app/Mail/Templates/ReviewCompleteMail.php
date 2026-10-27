<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Review;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class ReviewCompleteMail extends TemplateMailable
{
    public Log $log;

    public function __construct(Review $review)
    {
        $this->setAdditionalData([
            'Reviewer Name' => $review->user->fullName,
            'Submission Title' => $review->submission->getMeta('title'),
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $review->submission]),
        ]);
        $this->setTranslatableData('Submission Title', $review->submission, 'title');

        $this->log = Log::make(
            name: 'email',
            subject: $review->submission,
            description: __('general.email_sent', ['name' => 'Reviewer Completed Review'])
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.review_complete.subject');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to editors when a reviewer completes a review';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.review_complete.body');
    }
}
