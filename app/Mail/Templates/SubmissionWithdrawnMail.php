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

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'Submission Withdrawn']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Submission Withdrawn';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when their submission is withdrawn';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>

            <p>We would like to let you know that your submission titled "{{ Submission Title }}" has been withdrawn. Thank you for the time and effort you put into this work, and for considering {{ Conference Title }} as a venue for your research.</p>
        HTML;
    }
}
