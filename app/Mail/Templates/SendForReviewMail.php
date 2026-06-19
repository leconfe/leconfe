<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class SendForReviewMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public Log $log;

    public function __construct(Submission $submission)
    {
        $this->setAdditionalData([
            'Conference Title' => $submission->scheduledConference->title,
            'Submission Title' => $submission->getMeta('title'),
            'Submission ID' => $submission->getKey(),
            'Submission Author' => $submission->user->fullName,
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $submission]),
        ]);

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => __('general.submission_sent_for_review')]),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'Your submission {{ Submission Title }} has been sent for review';
    }

    public static function getDefaultDescription(): string
    {
        return 'Submission Sent for Review';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>Your submission "{{ Submission Title }}" to {{ Conference Title }} has been sent to the review stage.</p>
            <p>The editorial team will notify you when the review process progresses.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
    HTML;
    }
}
