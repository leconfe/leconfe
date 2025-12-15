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

        $this->log = Log::make(
            subject: $submission,
            name: 'email',
            description: __('general.email_sent', ['name' => 'Submission Published']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Submission Published';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when their submission is published';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>Your submission "{{ Submission Title }}" has been published on {{ Conference Title }}</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
        HTML;
    }
}
