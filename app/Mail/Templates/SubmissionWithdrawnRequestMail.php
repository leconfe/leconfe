<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class SubmissionWithdrawnRequestMail extends TemplateMailable
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
            description: __('general.email_sent', ['name' => 'Submission Withdraw Request']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'Submission Withdraw Request';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when their submission is withdrawn';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>We wanted to inform you that the submission "{{ Submission Title }}" has been requested to be withdrawn.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
        HTML;
    }
}
