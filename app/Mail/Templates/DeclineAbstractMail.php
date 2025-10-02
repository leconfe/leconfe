<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class DeclineAbstractMail extends TemplateMailable
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
            description: __('general.email_sent', ['name' => 'Abstract Declined']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Your submission {{ Submission Title }} on {{ Conference Title }} is declined.';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email template is sent when an abstract is declined.';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>We have reached a decision regarding your submission with title "{{ Submission Title }}"  to {{ Conference Title }}</p>
            <p>Our decision is to <b>decline the submission</b>.</p>
            <p>Thank you for considering {{ Conference Title }} as a venue for your work.</p>
    HTML;
    }
}
