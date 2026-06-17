<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class RevisionRequestMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public Log $log;

    public function __construct(protected Submission $submission)
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
            description: __('general.email_sent', ['name' => 'Revision Requested']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'Revision Requested for {{ Submission Title }}';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when their submission is requested for revision';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>Your submission with title "{{ Submission Title }}" on  has been requested to be <b>Revised</b></p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a> and upload your submission revision.</p>
        HTML;
    }
}
