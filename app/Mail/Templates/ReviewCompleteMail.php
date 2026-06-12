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

        $this->log = Log::make(
            name: 'email',
            subject: $review->submission,
            description: __('general.email_sent', ['name' => 'Reviewer Completed Review'])
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'Reviewer {{ Reviewer Name }} has completed a review on submission {{ Submission Title }}';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to editors when a reviewer completes a review';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Reviewer {{ Reviewer Name }} has completed a review on submission {{ Submission Title }}</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
        HTML;
    }
}
