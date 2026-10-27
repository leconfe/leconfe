<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class PublishSubmissionMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public string $title;

    public string $authorName;

    public string $loginLink;

    public Log $log;

    public function __construct(protected Submission $submission)
    {
        $this->setAdditionalData([
            'Conference Title' => $submission->scheduledConference->title,
            'Submission Title' => $submission->getMeta('title'),
            'Submission Author' => $submission->user->fullName,
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $submission]),
        ]);
        $this->setTranslatableData('Submission Title', $submission, 'title');
        $this->setTranslatableData('Conference Title', $submission->scheduledConference, 'title');

        $this->log = Log::make(
            subject: $submission,
            name: 'email',
            description: __('general.email_sent', ['name' => 'Submission Published']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.publish_submission.subject');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when their submission is published';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.publish_submission.body');
    }
}
