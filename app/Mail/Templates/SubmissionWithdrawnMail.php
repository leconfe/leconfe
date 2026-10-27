<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class SubmissionWithdrawnMail extends TemplateMailable
{
    public Log $log;

    public function __construct(Submission $submission)
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
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'Submission Withdrawn']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.submission_withdrawn.subject');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when their submission is withdrawn';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.submission_withdrawn.body');
    }
}
