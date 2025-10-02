<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class AcceptAbstractMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public Log $log;

    public function __construct(Submission $submission)
    {
        $this->setAdditionalData([
            'Conference Title' => $submission->payment->scheduledConference->title,
            'Submission Title' => $submission->getMeta('title'),
            'Submission ID' => $submission->getKey(),
            'Submission Author' => $submission->user->fullName,
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $submission]),
        ]);

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'Abstract Accepted']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Your submission {{ Submission Title }} has entered the review stage';
    }

    public static function getDefaultDescription(): string
    {
        return 'Abstract Accepted and Send to Review';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>We have reached a decision regarding your submission with title "{{ Submission Title }}"  to {{ Conference Title }}</p>
            <p>Our decision is to <b>proceed your submission to review stage.</b></p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
    HTML;
    }
}
