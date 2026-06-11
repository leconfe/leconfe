<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class AcceptPaperMail extends TemplateMailable
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
            'Submission ID' => $submission->getKey(),
            'Submission Author' => $submission->user->fullName,
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $submission]),
        ]);

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'Paper Accepted']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'Your submission {{ Submission Title }} on {{ Conference Title }} is accepted.';

    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when their submission is accepted';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>We have reached a decision regarding your submission with title "{{ Submission Title }}"  to {{ Conference Title }}</p>
            <p>Our decision is to <b>accept the submission</b>.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
    HTML;
    }
}
