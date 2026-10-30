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
        $this->setTranslatableData('Submission Title', $submission, 'title');
        $this->setTranslatableData('Conference Title', $submission->scheduledConference, 'title');

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => __('general.submission_sent_for_review')]),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.send_for_review.subject');
    }

    public static function getDefaultDescription(): string
    {
        return 'Submission Sent for Review';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.send_for_review.body');
    }
}
