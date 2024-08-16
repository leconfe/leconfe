<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Review;

class ReviewCompleteMail extends TemplateMailable
{
    public string $reviewer;

    public string $submissionTitle;

    public Log $log;

    public function __construct(Review $review)
    {
        $this->reviewer = $review->user->fullName;
        $this->submissionTitle = $review->submission->getMeta('title');

        $this->log = Log::make(
            name: 'email',
            subject: $review->submission,
            description: __('general.email_sent', ['name' => 'Reviewer Completed Review'])
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Reviewer Completed Review of Submission';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to editors when a reviewer completes a review';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>This is a automatic notification to let you know that {{ reviewer }} has completed the review of the submission titled "{{ submissionTitle }}".</p>
        HTML;
    }
}
