<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class ThankAuthorMail extends TemplateMailable
{
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
            description: __('general.email_sent', ['name' => 'Thank Author']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Thank you for your submission to {{ Conference Title }}';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email template is sent when a new submission is created.';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>Thank you for your recent submission with title "{{ Submission Title }}" to {{ Conference Title }}. We appreciate your interest in participating in our conference.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            <p>We have received your submission and it will be reviewed by our team. You will be notified of the outcome of the review process in due course.</p>
            <p>If you have any questions or need further information, please do not hesitate to contact us.</p>
        HTML;
    }
}
